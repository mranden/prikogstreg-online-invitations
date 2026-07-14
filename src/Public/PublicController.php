<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicEntitlement;
use PrikOgStreg\OnlineInvitations\Domain\Wishlist\WishlistReservationService;
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
		private WishlistReservationService $wishlist
	) {}

	public function register(): void {
		add_action( 'template_redirect', [ $this, 'maybe_render_invitation' ], 1 );
	}

	public function maybe_render_invitation(): void {
		$raw_token = $this->current_token();
		if ( '' === $raw_token ) {
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
		if ( empty( $content['success'] ) ) {
			$this->render_unavailable();
		}

		$this->open_tracker->maybe_track( $resolution );

		$wishlist = $this->build_wishlist_context( $resolution, $raw_token );
		$photos   = $this->build_photos_context( $resolution, $raw_token );
		$view_model = EnvelopeViewModel::from_resolution( $resolution, (string) ( $content['html'] ?? '' ), $raw_token, $wishlist, $photos );

		$this->send_privacy_headers();
		$this->enqueue_assets( $resolution->project() );

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
	private function enqueue_assets( array $project ): void {
		wp_enqueue_style(
			'pks-oi-public',
			PKS_OI_PLUGIN_URL . 'assets/build/css/public.css',
			[],
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
					'submitting' => __( 'Saving your response…', 'prikogstreg-online-invitations' ),
					'saved'      => __( 'Your response has been saved.', 'prikogstreg-online-invitations' ),
					'error'      => __( 'We could not save your response. Please try again.', 'prikogstreg-online-invitations' ),
					'wishlist_saved' => __( 'Wishlist updated.', 'prikogstreg-online-invitations' ),
					'wishlist_error' => __( 'We could not update the wishlist. Please try again.', 'prikogstreg-online-invitations' ),
					'photos_uploaded' => __( 'Photos uploaded. They will appear after organiser approval.', 'prikogstreg-online-invitations' ),
					'photos_uploading' => __( 'Uploading photos…', 'prikogstreg-online-invitations' ),
					'photos_error'    => __( 'We could not upload your photos. Please try again.', 'prikogstreg-online-invitations' ),
				],
			]
		);

		$adapter = $this->builder->get_adapter();
		if ( null !== $adapter && method_exists( $adapter, 'enqueue_public_assets' ) ) {
			$adapter->enqueue_public_assets(
				[
					'product_id'  => (int) ( $project['product_id'] ?? 0 ),
					'template_id' => (int) ( $project['product_id'] ?? 0 ),
					'locale'      => (string) ( $project['locale'] ?? 'da_DK' ),
					'mode'        => 'public',
					'is_public'   => true,
				]
			);
		}
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
				'intent_url'    => '',
				'upload_url'    => '',
				'rest_nonce'    => '',
				'requires_name' => false,
				'max_files'     => 10,
			];
		}

		$base = '';
		if ( '' !== $raw_token && function_exists( 'rest_url' ) ) {
			$encoded = rawurlencode( $raw_token );
			$base    = rest_url( 'prikogstreg-online-invitations/v1/public/' . $encoded . '/photos' );
		}

		return [
			'intent_url'    => '' !== $base ? $base . '/intent' : '',
			'upload_url'    => '' !== $base ? $base . '/upload' : '',
			'rest_nonce'    => function_exists( 'wp_create_nonce' ) ? wp_create_nonce( 'wp_rest' ) : '',
			'requires_name' => $resolution->is_generic(),
			'max_files'     => 10,
		];
	}
}
