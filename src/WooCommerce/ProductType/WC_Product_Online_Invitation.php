<?php

declare(strict_types=1);

/**
 * WooCommerce product class for online invitation products.
 */
class WC_Product_Online_Invitation extends WC_Product_Simple {

	/**
	 * @param int|WC_Product|object $product Product identifier or object.
	 */
	public function __construct( $product = 0 ) {
		$this->product_type = \PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta::TYPE;
		parent::__construct( $product );
	}

	public function get_type(): string {
		return \PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta::TYPE;
	}

	public function is_virtual( $context = 'view' ): bool {
		return true;
	}

	public function is_sold_individually( $context = 'view' ): bool {
		return true;
	}

	public function get_min_purchase_quantity(): int {
		return 1;
	}

	public function get_max_purchase_quantity( $qty = -1, $variation_id = 0 ): int {
		return 1;
	}
}
