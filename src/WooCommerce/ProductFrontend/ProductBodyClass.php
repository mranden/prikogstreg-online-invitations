<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\ProductFrontend;

use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\BuilderValidity;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;

/**
 * Adds generic body classes for online_invitation product-page layout coordination.
 */
final class ProductBodyClass {

	public function __construct(
		private BuilderFrontendBridge $builder_bridge
	) {}

	public function register(): void {
		add_filter( 'body_class', [ $this, 'filter_body_class' ] );
	}

	/**
	 * @param list<string> $classes
	 * @return list<string>
	 */
	public function filter_body_class( array $classes ): array {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return $classes;
		}

		$product = wc_get_product();
		if ( ! $product || ! ProductMeta::is_online_invitation( $product ) ) {
			return $classes;
		}

		$classes[] = 'pks-oi-product-page';
		$classes[] = 'pks-oi-product-workspace';

		$product_id = (int) $product->get_id();
		if ( $this->builder_bridge->has_active_template( $product_id ) && BuilderValidity::has_template_pages( $product_id ) ) {
			$classes[] = 'pks-oi-has-builder-canvas';
		}

		if ( $this->builder_bridge->is_customizable_product( $product ) ) {
			$classes[] = 'pks-oi-product-configurator-active';
		}

		return $classes;
	}
}
