<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\ProductType;

/**
 * Enforces quantity-one rules for online_invitation products.
 */
final class QuantityGuard {

	public function register(): void {
		add_filter( 'woocommerce_quantity_input_args', [ $this, 'product_page_quantity_args' ], 10, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_add_to_cart' ], 10, 5 );
		add_filter( 'woocommerce_update_cart_validation', [ $this, 'validate_cart_update' ], 10, 4 );
		add_action( 'woocommerce_add_to_cart', [ $this, 'normalize_added_quantity' ], 10, 6 );
		add_filter( 'woocommerce_store_api_product_quantity_limits', [ $this, 'store_api_limits' ], 10, 2 );
		add_filter( 'woocommerce_cart_item_quantity', [ $this, 'cart_item_quantity_html' ], 10, 3 );
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	public function product_page_quantity_args( array $args, $product ): array {
		if ( ! $this->is_invitation_product( $product ) ) {
			return $args;
		}

		$args['min_value']   = 1;
		$args['max_value']   = 1;
		$args['input_value'] = 1;

		return $args;
	}

	public function validate_add_to_cart( bool $passed, $product_id, $quantity, $variation_id = 0, $variations = [] ): bool {
		$product = wc_get_product( $product_id );
		if ( ! $product || ! ProductMeta::is_online_invitation( $product ) ) {
			return $passed;
		}

		if ( (int) $quantity !== 1 ) {
			wc_add_notice(
				__( 'Online invitations can only be purchased one at a time.', 'prikogstreg-online-invitations' ),
				'error'
			);

			return false;
		}

		if ( ! BuilderValidity::is_valid( (int) $product_id ) ) {
			wc_add_notice(
				__( 'This online invitation product is not ready for purchase yet.', 'prikogstreg-online-invitations' ),
				'error'
			);

			return false;
		}

		return $passed;
	}

	public function validate_cart_update( bool $passed, $cart_item_key, $values, $quantity ): bool {
		$product = $values['data'] ?? null;
		if ( ! $product || ! ProductMeta::is_online_invitation( $product ) ) {
			return $passed;
		}

		if ( (int) $quantity !== 1 ) {
			wc_add_notice(
				__( 'Online invitation quantity cannot be changed.', 'prikogstreg-online-invitations' ),
				'error'
			);

			return false;
		}

		return $passed;
	}

	public function normalize_added_quantity( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ): void {
		if ( 1 === (int) $quantity ) {
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! ProductMeta::is_online_invitation( $product ) ) {
			return;
		}

		if ( function_exists( 'WC' ) && WC()->cart ) {
			WC()->cart->set_quantity( $cart_item_key, 1, false );
		}
	}

	/**
	 * @param array<string, mixed> $limits
	 * @return array<string, mixed>
	 */
	public function store_api_limits( array $limits, $product ): array {
		if ( ! $this->is_invitation_product( $product ) ) {
			return $limits;
		}

		return [
			'minimum'     => 1,
			'maximum'     => 1,
			'multiple_of' => 1,
			'editable'    => false,
		];
	}

	public function cart_item_quantity_html( string $quantity_html, $cart_item_key, $cart_item ): string {
		$product = $cart_item['data'] ?? null;
		if ( ! $product || ! ProductMeta::is_online_invitation( $product ) ) {
			return $quantity_html;
		}

		return '1 <input type="hidden" name="cart[' . esc_attr( $cart_item_key ) . '][qty]" value="1" />';
	}

	private function is_invitation_product( $product ): bool {
		return is_object( $product ) && ProductMeta::is_online_invitation( $product );
	}
}
