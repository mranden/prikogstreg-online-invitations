<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\ProductType;

/**
 * Renders a storefront placeholder when PDF Builder is intentionally disconnected.
 */
final class ProductPagePlaceholder {

	private static bool $rendered = false;

	public function register(): void {
		add_action( 'woocommerce_before_single_product_summary', [ $this, 'maybe_render' ], 5 );
	}

	public function maybe_render(): void {
		if ( self::$rendered || ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$product = wc_get_product();
		if ( ! $product || ! ProductMeta::is_builder_optional( $product ) ) {
			return;
		}

		self::$rendered = true;

		wp_enqueue_style(
			'pks-oi-public',
			PKS_OI_PLUGIN_URL . 'assets/build/css/public.css',
			[],
			PKS_OI_VERSION
		);

		echo '<div id="customizer-area" class="pks-oi-product-builder-placeholder">';
		echo '<div class="pks-oi-product-builder-placeholder__frame">';
		echo '<p class="pks-oi-product-builder-placeholder__label">';
		esc_html_e( 'PDF / Online invitation will show here', 'prikogstreg-online-invitations' );
		echo '</p>';
		echo '</div>';
		echo '</div>';
	}
}
