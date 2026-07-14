<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\ProductFrontend;

use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;

/**
 * Enqueues storefront assets for online_invitation product pages.
 */
final class ProductFrontendAssets {

	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue' ], 20 );
	}

	public function maybe_enqueue(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$product = wc_get_product();
		if ( ! $product || ! ProductMeta::is_online_invitation( $product ) ) {
			return;
		}

		wp_enqueue_style(
			'pks-oi-product',
			PKS_OI_PLUGIN_URL . 'assets/build/css/product.css',
			[],
			PKS_OI_VERSION
		);

		wp_enqueue_script(
			'pks-oi-product',
			PKS_OI_PLUGIN_URL . 'assets/build/js/product.js',
			[],
			PKS_OI_VERSION,
			true
		);
	}

	/**
	 * Test helper: whether OI product assets would enqueue for the current request.
	 */
	public function should_enqueue_for_product( ?object $product ): bool {
		if ( ! $product || ! ProductMeta::is_online_invitation( $product ) ) {
			return false;
		}

		return true;
	}
}
