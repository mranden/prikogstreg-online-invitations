<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\ProductType;

/**
 * PDF Builder integration hooks for online_invitation products.
 */
final class BuilderIntegration {

	public function register(): void {
		add_filter( 'bpp/is_product_customizable', [ $this, 'filter_product_customizable' ], 10, 2 );
		add_filter( 'woocommerce_product_is_purchasable', [ $this, 'filter_purchasable' ], 10, 2 );
	}

	public function filter_product_customizable( bool $customizable, int $product_id ): bool {
		$product = wc_get_product( $product_id );
		if ( ! $product || ! ProductMeta::is_online_invitation( $product ) ) {
			return $customizable;
		}

		return BuilderValidity::has_active_builder_template( $product_id );
	}

	public function filter_purchasable( bool $purchasable, $product ): bool {
		if ( ! $product || ! ProductMeta::is_online_invitation( $product ) ) {
			return $purchasable;
		}

		return $purchasable && BuilderValidity::is_valid( (int) $product->get_id() );
	}
}
