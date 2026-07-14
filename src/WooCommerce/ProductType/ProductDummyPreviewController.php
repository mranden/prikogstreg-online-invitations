<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\ProductType;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Public\Endpoints;
use PrikOgStreg\OnlineInvitations\Public\PosterDisplayAssets;
use PrikOgStreg\OnlineInvitations\Public\PublicThemeFonts;
use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;

/**
 * Serves /invitation-sample/{product_id}/ with synthetic guest data.
 */
final class ProductDummyPreviewController {

	public function __construct(
		private BuilderService $builder,
		private TemplateLoader $templates
	) {}

	public function register(): void {
		add_action( 'template_redirect', [ $this, 'maybe_render' ], 1 );
	}

	public function maybe_render(): void {
		$product_id = $this->current_product_id();
		if ( $product_id <= 0 ) {
			return;
		}

		$service = new ProductDummyPreviewService( $this->builder );
		$view    = $service->build_view_model( $product_id );
		if ( null === $view ) {
			$this->render_unavailable();
		}

		$this->send_privacy_headers();
		$this->enqueue_assets( $product_id );

		$this->templates->render(
			'public/invitation',
			[
				'view'              => $view,
				'is_sample_preview' => true,
			]
		);
		exit;
	}

	public static function preview_url( int $product_id ): string {
		return Endpoints::product_sample_url( max( 0, $product_id ) );
	}

	private function current_product_id(): int {
		$raw = get_query_var( Endpoints::PRODUCT_SAMPLE_QUERY_VAR );
		if ( is_numeric( $raw ) ) {
			return max( 0, (int) $raw );
		}

		if ( isset( $_GET[ Endpoints::PRODUCT_SAMPLE_QUERY_VAR ] ) ) {
			return max( 0, (int) $_GET[ Endpoints::PRODUCT_SAMPLE_QUERY_VAR ] );
		}

		return 0;
	}

	private function send_privacy_headers(): void {
		if ( headers_sent() ) {
			return;
		}

		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'Pragma: no-cache', true );
	}

	private function enqueue_assets( int $product_id ): void {
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
					'submitting'       => __( 'Saving your response…', 'prikogstreg-online-invitations' ),
					'saved'            => __( 'Your response has been saved.', 'prikogstreg-online-invitations' ),
					'error'            => __( 'We could not save your response. Please try again.', 'prikogstreg-online-invitations' ),
					'wishlist_saved'   => __( 'Wishlist updated.', 'prikogstreg-online-invitations' ),
					'wishlist_error'   => __( 'We could not update the wishlist. Please try again.', 'prikogstreg-online-invitations' ),
					'photos_uploaded'  => __( 'Photos uploaded. They will appear after organiser approval.', 'prikogstreg-online-invitations' ),
					'photos_uploading' => __( 'Uploading photos…', 'prikogstreg-online-invitations' ),
					'photos_error'     => __( 'We could not upload your photos. Please try again.', 'prikogstreg-online-invitations' ),
					'personal_link'    => __( 'Personal link (save for later):', 'prikogstreg-online-invitations' ),
					'poster_prev'      => __( 'Previous page', 'prikogstreg-online-invitations' ),
					'poster_next'      => __( 'Next page', 'prikogstreg-online-invitations' ),
					'poster_page'      => __( 'Page %1$d of %2$d', 'prikogstreg-online-invitations' ),
					'preview_notice'   => __( 'This is a sample preview with dummy data. Responses are not saved.', 'prikogstreg-online-invitations' ),
				],
			]
		);

		$poster_assets = new PosterDisplayAssets( \PrikOgStreg\OnlineInvitations\Plugin::instance()->storage()->project_storage() );
		$poster_assets->enqueue(
			[
				'product_id'   => $product_id,
				'storage_uuid' => '',
				'locale'       => ProductMeta::DEFAULT_LOCALE_VALUE,
			],
			''
		);
	}

	private function render_unavailable(): void {
		$this->send_privacy_headers();
		status_header( 404 );
		$this->templates->render(
			'public/unavailable',
			[
				'message' => __( 'This sample preview is not available.', 'prikogstreg-online-invitations' ),
			]
		);
		exit;
	}
}
