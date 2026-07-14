<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoShareTokenService;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicEntitlement;
use PrikOgStreg\OnlineInvitations\Domain\Wishlist\WishlistReservationService;
use PrikOgStreg\OnlineInvitations\Storage\EnvelopeManifest;
use PrikOgStreg\OnlineInvitations\Storage\FileStreamResponse;
use PrikOgStreg\OnlineInvitations\Storage\ProjectStorage;
use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;

/**
 * Serves /invitation/{token}/ with uniform unavailable responses.
 */
final class PublicController {

	public function __construct(
		private TokenResolver $resolver,
		private PublicInvitationLoader $loader,
		private OpenTracker $open_tracker,
		private InvalidTokenRateLimiter $rate_limiter,
		private TemplateLoader $templates,
		private BuilderService $builder,
		private WishlistReservationService $wishlist,
		private ProjectStorage $storage,
		private FileStreamResponse $streams,
		private EnvelopeImageResolver $envelope_images,
		private PosterDisplayAssets $poster_assets,
		private PhotoShareTokenService $photo_share_tokens
	) {}

	public function register(): void {
		add_action( 'template_redirect', [ $this, 'maybe_stream_poster_asset' ], 0 );
		add_action( 'template_redirect', [ $this, 'maybe_stream_envelope_image' ], 0 );
		add_action( 'template_redirect', [ $this, 'maybe_render_invitation' ], 1 );
	}

	public function maybe_stream_poster_asset(): void {
		$asset = $this->current_poster_asset();
		if ( '' === $asset ) {
			return;
		}

		$raw_token = $this->current_token();
		if ( '' === $raw_token ) {
			$this->render_unavailable();
		}

		$resolution = $this->resolver->resolve( $raw_token );
		if ( null === $resolution || ! PublicEntitlement::is_publicly_available( $resolution->project() ) ) {
			$this->render_unavailable();
		}

		$project      = $resolution->project();
		$storage_uuid = (string) ( $project['storage_uuid'] ?? '' );
		$manifest     = $this->storage->try_read_poster_manifest( $storage_uuid );
		if ( null === $manifest ) {
			$this->render_unavailable();
		}

		$relative_path = 'display' === $asset ? $manifest->display_css_path : $manifest->fonts_css_path;
		$checksum      = 'display' === $asset ? $manifest->display_css_sha256 : $manifest->fonts_css_sha256;

		if ( null === $relative_path || '' === $relative_path ) {
			$this->render_unavailable();
		}

		try {
			$handle = $this->streams->open_relative(
				$storage_uuid,
				$relative_path,
				'text/css; charset=utf-8',
				$checksum
			);
		} catch ( \Throwable ) {
			$this->render_unavailable();
		}

		$this->send_privacy_headers();
		if ( ! headers_sent() ) {
			header( 'Content-Type: ' . $handle->mime_type, true );
			header( 'Content-Length: ' . (string) $handle->byte_size, true );
		}

		if ( is_resource( $handle->stream ) ) {
			fpassthru( $handle->stream );
		}

		exit;
	}

	public function maybe_stream_envelope_image(): void {
		if ( ! $this->is_envelope_asset_request() ) {
			return;
		}

		$raw_token = $this->current_token();
		if ( '' === $raw_token ) {
			$this->render_unavailable();
		}

		$resolution = $this->resolver->resolve( $raw_token );
		if ( null === $resolution || ! PublicEntitlement::is_publicly_available( $resolution->project() ) ) {
			$this->render_unavailable();
		}

		$project      = $resolution->project();
		$storage_uuid = (string) ( $project['storage_uuid'] ?? '' );
		$manifest     = $this->storage->try_read_envelope_manifest( $storage_uuid );
		if (
			null === $manifest
			|| EnvelopeManifest::MEDIA_PROJECT_COPY !== $manifest->media_storage
			|| null === $manifest->image_path
			|| '' === $manifest->image_path
		) {
			$this->render_unavailable();
		}

		try {
			$handle = $this->streams->open_relative(
				$storage_uuid,
				$manifest->image_path,
				$this->guess_mime_from_path( $manifest->image_path ),
				$manifest->image_sha256
			);
		} catch ( \Throwable ) {
			$this->render_unavailable();
		}

		$this->send_privacy_headers();
		if ( ! headers_sent() ) {
			header( 'Content-Type: ' . $handle->mime_type, true );
			header( 'Content-Length: ' . (string) $handle->byte_size, true );
		}

		if ( is_resource( $handle->stream ) ) {
			fpassthru( $handle->stream );
		}

		exit;
	}

	public function maybe_render_invitation(): void {
		$raw_token = $this->current_token();
		if ( '' === $raw_token || '' !== $this->current_poster_asset() || $this->is_envelope_asset_request() ) {
			return;
		}

		$client_key = $this->rate_limiter->client_key_from_request();
		if ( $this->rate_limiter->is_limited( $client_key ) ) {
			$this->render_unavailable();
		}

		$resolution = $this->resolver->resolve( $raw_token );
		if ( null === $resolution || ! PublicEntitlement::is_publicly_available( $resolution->project() ) ) {
			$this->rate_limiter->record_failure( $client_key );
			$this->render_unavailable();
		}

		$content = $this->loader->load_published_content( $resolution->project() );
		if ( empty( $content['success'] ) || ! isset( $content['content'] ) || ! $content['content'] instanceof PublicInvitationContent ) {
			$error = (string) ( $content['error'] ?? '' );
			if ( 'empty_published_html' === $error ) {
				$this->render_empty_poster_unavailable( $resolution );
			}
			$this->render_unavailable();
		}

		$this->open_tracker->maybe_track( $resolution );

		$wishlist = $this->build_wishlist_context( $resolution, $raw_token );
		$photos   = $this->build_photos_context( $resolution, $raw_token );
		$view_model = EnvelopeViewModel::from_resolution(
			$resolution,
			$content['content'],
			$raw_token,
			$wishlist,
			$photos,
			$this->envelope_images,
			$this->storage
		);

		$this->send_privacy_headers();
		$this->enqueue_assets( $resolution->project(), $raw_token );

		$this->templates->render(
			'public/invitation',
			[
				'view' => $view_model,
			]
		);
		exit;
	}

	private function current_token(): string {
		$token = get_query_var( Endpoints::QUERY_VAR );
		if ( is_string( $token ) && '' !== $token ) {
			return rawurldecode( $token );
		}

		if ( isset( $_GET[ Endpoints::QUERY_VAR ] ) ) {
			return rawurldecode( sanitize_text_field( wp_unslash( (string) $_GET[ Endpoints::QUERY_VAR ] ) ) );
		}

		return '';
	}

	private function current_poster_asset(): string {
		$asset = get_query_var( Endpoints::POSTER_ASSET_QUERY_VAR );
		if ( is_string( $asset ) && in_array( $asset, [ 'display', 'fonts' ], true ) ) {
			return $asset;
		}

		if ( isset( $_GET[ Endpoints::POSTER_ASSET_QUERY_VAR ] ) ) {
			$asset = sanitize_key( wp_unslash( (string) $_GET[ Endpoints::POSTER_ASSET_QUERY_VAR ] ) );
			if ( in_array( $asset, [ 'display', 'fonts' ], true ) ) {
				return $asset;
			}
		}

		return '';
	}

	private function is_envelope_asset_request(): bool {
		$flag = get_query_var( Endpoints::ENVELOPE_ASSET_QUERY_VAR );
		if ( '1' === (string) $flag ) {
			return true;
		}

		return isset( $_GET[ Endpoints::ENVELOPE_ASSET_QUERY_VAR ] )
			&& '1' === sanitize_text_field( wp_unslash( (string) $_GET[ Endpoints::ENVELOPE_ASSET_QUERY_VAR ] ) );
	}

	private function guess_mime_from_path( string $relative_path ): string {
		$extension = strtolower( pathinfo( $relative_path, PATHINFO_EXTENSION ) );

		return match ( $extension ) {
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			default => 'image/jpeg',
		};
	}

	private function render_unavailable(): void {
		$this->send_privacy_headers();
		status_header( 404 );
		$this->templates->render(
			'public/unavailable',
			[
				'message' => __( 'This invitation is not available.', 'prikogstreg-online-invitations' ),
			]
		);
		exit;
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function render_empty_poster_unavailable( TokenResolution $resolution ): void {
		$project = $resolution->project();
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;

		$is_owner_or_staff = $user_id > 0 && (
			$user_id === (int) ( $project['user_id'] ?? 0 )
			|| ( function_exists( 'current_user_can' ) && current_user_can( 'pks_oi_support_projects' ) )
		);

		if ( function_exists( 'error_log' ) ) {
			error_log(
				sprintf(
					'pks_oi empty published poster project_id=%d owner_view=%s',
					(int) ( $project['project_id'] ?? 0 ),
					$is_owner_or_staff ? 'yes' : 'no'
				)
			);
		}

		$this->send_privacy_headers();
		status_header( 404 );

		if ( $is_owner_or_staff ) {
			$message = __(
				'The invitation design could not be displayed. Open My Account, review your design, and republish the project.',
				'prikogstreg-online-invitations'
			);
		} else {
			$message = __(
				'This invitation is temporarily unavailable. Please try again later or contact the organiser.',
				'prikogstreg-online-invitations'
			);
		}

		$this->templates->render(
			'public/unavailable',
			[
				'message' => $message,
			]
		);
		exit;
	}

	private function send_privacy_headers(): void {
		if ( headers_sent() ) {
			return;
		}

		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'Pragma: no-cache', true );
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function enqueue_assets( array $project, string $raw_token ): void {
		PublicThemeFonts::enqueue();

		wp_enqueue_style(
			'pks-oi-public',
			PKS_OI_PLUGIN_URL . 'assets/build/css/public.css',
			[ 'pks-oi-theme-fonts' ],
			PKS_OI_VERSION
		);

		wp_enqueue_script(
			'pks-oi-public',
			PKS_OI_PLUGIN_URL . 'assets/build/js/public.js',
			[],
			PKS_OI_VERSION,
			true
		);

		wp_localize_script(
			'pks-oi-public',
			'pksOiPublic',
			[
				'i18n' => [
					'submitting'      => __( 'Saving your response…', 'prikogstreg-online-invitations' ),
					'saved'           => __( 'Your response has been saved.', 'prikogstreg-online-invitations' ),
					'error'           => __( 'We could not save your response. Please try again.', 'prikogstreg-online-invitations' ),
					'wishlist_saved'  => __( 'Wishlist updated.', 'prikogstreg-online-invitations' ),
					'wishlist_error'  => __( 'We could not update the wishlist. Please try again.', 'prikogstreg-online-invitations' ),
					'photos_uploaded' => __( 'Photos uploaded. They will appear after organiser approval.', 'prikogstreg-online-invitations' ),
					'photos_uploading'=> __( 'Uploading photos…', 'prikogstreg-online-invitations' ),
					'photos_error'    => __( 'We could not upload your photos. Please try again.', 'prikogstreg-online-invitations' ),
					'personal_link'   => __( 'Personal link (save for later):', 'prikogstreg-online-invitations' ),
					'poster_prev'     => __( 'Previous page', 'prikogstreg-online-invitations' ),
					'poster_next'     => __( 'Next page', 'prikogstreg-online-invitations' ),
					'poster_page'     => __( 'Page %1$d of %2$d', 'prikogstreg-online-invitations' ),
				],
			]
		);

		$this->poster_assets->enqueue( $project, $raw_token );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_wishlist_context( TokenResolution $resolution, string $raw_token ): array {
		$project = $resolution->project();
		if ( ! PublicEntitlement::is_publicly_available( $project ) || empty( $project['internal_wishlist_enabled'] ) ) {
			return [
				'items'         => [],
				'external_url'  => (string) ( $project['external_wishlist_url'] ?? '' ),
				'rest_base'     => '',
				'rest_nonce'    => '',
				'requires_name' => false,
			];
		}

		$rest_base = '';
		if ( '' !== $raw_token && function_exists( 'rest_url' ) ) {
			$rest_base = rest_url( 'prikogstreg-online-invitations/v1/public/' . rawurlencode( $raw_token ) . '/wishlist' );
		}

		return [
			'items'         => $this->wishlist->list_public_items( $resolution ),
			'external_url'  => (string) ( $project['external_wishlist_url'] ?? '' ),
			'rest_base'     => $rest_base,
			'rest_nonce'    => function_exists( 'wp_create_nonce' ) ? wp_create_nonce( 'wp_rest' ) : '',
			'requires_name' => $resolution->is_generic(),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_photos_context( TokenResolution $resolution, string $raw_token ): array {
		$project = $resolution->project();
		if ( ! PublicEntitlement::is_publicly_available( $project ) || empty( $project['guest_photos_enabled'] ) ) {
			return [
				'share_url' => '',
			];
		}

		$raw_share = $this->photo_share_tokens->resolve_raw_token( $project );
		if ( null === $raw_share ) {
			$ensured   = $this->photo_share_tokens->ensure_token( $project );
			$raw_share = (string) ( $ensured['token'] ?? '' );
		}

		return [
			'share_url' => '' !== $raw_share ? PhotoShareTokenService::public_url( $raw_share ) : '',
		];
	}
}
