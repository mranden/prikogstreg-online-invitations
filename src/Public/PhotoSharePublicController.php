<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoGuestSessionService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoShareEntitlement;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoShareTokenService;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;
use PrikOgStreg\OnlineInvitations\Storage\FileStreamResponse;
use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;

/**
 * Serves /photos/{token}/ landing page and protected image streams.
 */
final class PhotoSharePublicController {

	public function __construct(
		private PhotoShareTokenService $share_tokens,
		private PhotoGuestSessionService $sessions,
		private PhotoService $photos,
		private InvalidTokenRateLimiter $rate_limiter,
		private FileStreamResponse $streams,
		private TemplateLoader $templates
	) {}

	public function register(): void {
		add_action( 'template_redirect', [ $this, 'maybe_stream_photo' ], 0 );
		add_action( 'template_redirect', [ $this, 'maybe_render_wall' ], 1 );
		add_action( 'template_redirect', [ $this, 'maybe_render_landing' ], 2 );
	}

	public function maybe_stream_photo(): void {
		$photo_id = $this->current_stream_id();
		if ( $photo_id <= 0 ) {
			return;
		}

		$raw_token = $this->current_token();
		if ( '' === $raw_token ) {
			$this->render_unavailable();
		}

		$project = $this->share_tokens->resolve_by_raw_token( $raw_token );
		if ( ! is_array( $project ) || ! PhotoShareEntitlement::is_gallery_public( $project ) ) {
			$this->render_unavailable();
		}

		$share_hash = InvitationToken::hash( $raw_token );
		$session    = $this->sessions->validate( $project, $share_hash );
		if ( null === $session && ! PhotoShareEntitlement::is_gallery_public( $project ) ) {
			status_header( 403 );
			exit;
		}

		$result = $this->photos->resolve_gallery_stream( $project, $photo_id, true );
		if ( empty( $result['success'] ) || ! is_array( $result['row'] ?? null ) ) {
			status_header( 404 );
			exit;
		}

		$row          = $result['row'];
		$use_thumb    = ! empty( $result['use_thumbnail'] );
		$relative     = $use_thumb ? (string) ( $row['thumbnail_path'] ?? '' ) : (string) ( $row['relative_path'] ?? '' );
		$mime         = $use_thumb ? 'image/jpeg' : (string) ( $row['mime_type'] ?? 'image/jpeg' );
		$storage_uuid = (string) ( $row['storage_uuid'] ?? '' );

		try {
			$handle = $this->streams->open_relative(
				$storage_uuid,
				$relative,
				$mime,
				$use_thumb ? '' : (string) ( $row['sha256'] ?? '' )
			);
		} catch ( \Throwable ) {
			status_header( 404 );
			exit;
		}

		$this->send_privacy_headers();
		header( 'Content-Type: ' . $handle->mime_type );
		header( 'Content-Length: ' . (string) $handle->byte_size );
		header( 'Cache-Control: private, no-store', true );

		if ( is_resource( $handle->stream ) ) {
			fpassthru( $handle->stream );
		}
		$handle->close();
		exit;
	}

	public function maybe_render_landing(): void {
		$raw_token = $this->current_token();
		if ( '' === $raw_token || $this->current_stream_id() > 0 || $this->is_wall_request() ) {
			return;
		}

		$client_key = $this->rate_limiter->client_key_from_request();
		if ( $this->rate_limiter->is_limited( $client_key ) ) {
			$this->render_unavailable();
		}

		$project = $this->share_tokens->resolve_by_raw_token( $raw_token );
		if ( ! is_array( $project ) || ! PhotoShareEntitlement::is_available( $project ) ) {
			$this->rate_limiter->record_failure( $client_key );
			$this->render_unavailable();
		}

		$share_hash = InvitationToken::hash( $raw_token );
		$session    = $this->sessions->validate( $project, $share_hash );
		$authorized = null !== $session;
		$auto_approve = PhotoShareEntitlement::auto_approve_enabled( $project );

		$rest_base = function_exists( 'rest_url' )
			? rest_url( 'prikogstreg-online-invitations/v1/photo-share/' . rawurlencode( $raw_token ) )
			: '';

		$this->send_privacy_headers();
		$this->set_document_title(
			'' !== trim( (string) ( $project['event_title'] ?? '' ) )
				? trim( (string) $project['event_title'] )
				: __( 'Event photos', 'prikogstreg-online-invitations' )
		);
		$this->enqueue_assets();

		$this->templates->render(
			'public/photo-share',
			[
				'project'           => $project,
				'event_title'       => trim( (string) ( $project['event_title'] ?? '' ) ),
				'organiser_name'    => trim( (string) ( $project['organiser_display_name'] ?? '' ) ),
				'authorized'        => $authorized,
				'upload_open'       => PhotoShareEntitlement::is_upload_open( $project ),
				'gallery_public'    => PhotoShareEntitlement::is_gallery_public( $project ),
				'auto_approve'      => $auto_approve,
				'wall_url'          => PhotoShareTokenService::wall_url( $raw_token ),
				'rest_base'         => $rest_base,
				'rest_nonce'        => function_exists( 'wp_create_nonce' ) ? wp_create_nonce( 'wp_rest' ) : '',
				'max_files'         => 10,
				'moderation_notice' => $auto_approve
					? __( 'Uploaded photos appear on the photo wall right away.', 'prikogstreg-online-invitations' )
					: __( 'Uploaded photos are reviewed by the organiser before they may appear in the gallery.', 'prikogstreg-online-invitations' ),
			]
		);
		exit;
	}

	public function maybe_render_wall(): void {
		$raw_token = $this->current_token();
		if ( '' === $raw_token || ! $this->is_wall_request() || $this->current_stream_id() > 0 ) {
			return;
		}

		$client_key = $this->rate_limiter->client_key_from_request();
		if ( $this->rate_limiter->is_limited( $client_key ) ) {
			$this->render_unavailable();
		}

		$project = $this->share_tokens->resolve_by_raw_token( $raw_token );
		if ( ! is_array( $project ) || ! PhotoShareEntitlement::is_gallery_public( $project ) ) {
			$this->rate_limiter->record_failure( $client_key );
			$this->render_unavailable(
				__( 'The photo wall is not available.', 'prikogstreg-online-invitations' )
			);
		}

		$rest_base = function_exists( 'rest_url' )
			? rest_url( 'prikogstreg-online-invitations/v1/photo-share/' . rawurlencode( $raw_token ) )
			: '';

		$this->send_privacy_headers();
		$event_title = trim( (string) ( $project['event_title'] ?? '' ) );
		$this->set_document_title(
			'' !== $event_title
				? $event_title . ' — ' . __( 'Photo wall', 'prikogstreg-online-invitations' )
				: __( 'Photo wall', 'prikogstreg-online-invitations' )
		);
		$this->enqueue_wall_assets();

		$this->templates->render(
			'public/photo-wall',
			[
				'project'        => $project,
				'event_title'    => trim( (string) ( $project['event_title'] ?? '' ) ),
				'organiser_name' => trim( (string) ( $project['organiser_display_name'] ?? '' ) ),
				'rest_base'      => $rest_base,
				'rest_nonce'     => function_exists( 'wp_create_nonce' ) ? wp_create_nonce( 'wp_rest' ) : '',
			]
		);
		exit;
	}

	private function current_token(): string {
		$token = get_query_var( PhotoShareEndpoints::QUERY_VAR );
		if ( is_string( $token ) && '' !== $token ) {
			return rawurldecode( $token );
		}

		return '';
	}

	private function current_stream_id(): int {
		$id = get_query_var( PhotoShareEndpoints::STREAM_QUERY_VAR );

		return is_numeric( $id ) ? (int) $id : 0;
	}

	private function is_wall_request(): bool {
		return '1' === (string) get_query_var( PhotoShareEndpoints::WALL_QUERY_VAR );
	}

	private function render_unavailable( ?string $message = null ): void {
		$this->send_privacy_headers();
		status_header( 404 );
		$this->templates->render(
			'public/unavailable',
			[
				'message' => $message ?? __( 'Photo sharing is not available.', 'prikogstreg-online-invitations' ),
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

	private function enqueue_assets(): void {
		$this->enqueue_public_style();
		wp_enqueue_script(
			'pks-oi-photo-share',
			PKS_OI_PLUGIN_URL . 'assets/build/js/photo-share-entry.js',
			[],
			PKS_OI_VERSION,
			true
		);

		wp_localize_script(
			'pks-oi-photo-share',
			'pksOiPhotoShare',
			[
				'i18n' => [
					'code_error'      => __( 'The photo code is incorrect. Please try again.', 'prikogstreg-online-invitations' ),
					'code_submitting' => __( 'Checking code…', 'prikogstreg-online-invitations' ),
					'uploading'       => __( 'Uploading…', 'prikogstreg-online-invitations' ),
					'uploaded'        => __( 'Photos uploaded.', 'prikogstreg-online-invitations' ),
					'upload_error'    => __( 'We could not upload your photos. Please try again.', 'prikogstreg-online-invitations' ),
					'copied'          => __( 'Link copied.', 'prikogstreg-online-invitations' ),
				],
			]
		);
	}

	private function enqueue_wall_assets(): void {
		$this->enqueue_public_style();
		wp_enqueue_script(
			'pks-oi-photo-wall',
			PKS_OI_PLUGIN_URL . 'assets/build/js/photo-wall-entry.js',
			[],
			PKS_OI_VERSION,
			true
		);
	}

	private function enqueue_public_style(): void {
		wp_enqueue_style(
			'pks-oi-public',
			PKS_OI_PLUGIN_URL . 'assets/build/css/public.css',
			[],
			PKS_OI_VERSION
		);
	}

	private function set_document_title( string $title ): void {
		add_filter(
			'pre_get_document_title',
			static function () use ( $title ): string {
				return $title;
			},
			100
		);
		add_filter(
			'document_title_parts',
			static function () use ( $title ): array {
				return [ 'title' => $title ];
			},
			100
		);
	}
}
