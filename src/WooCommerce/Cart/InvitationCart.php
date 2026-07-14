<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\Cart;

use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\BppAttributeDefaults;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;

/**
 * Preserves invitation cart markers and validates builder payload at add-to-cart.
 */
final class InvitationCart {

	public function __construct(
		private CartPayloadValidator $validator
	) {}

	public function register(): void {
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_builder_payload' ], 15, 5 );
		add_filter( 'woocommerce_add_cart_item_data', [ $this, 'annotate_invitation_line' ], 100, 3 );
		add_filter( 'woocommerce_get_cart_item_from_session', [ $this, 'restore_from_session' ], 10, 2 );
	}

	public function validate_builder_payload( bool $passed, $product_id, $quantity, $variation_id = 0, $variations = [] ): bool {
		if ( ! $passed ) {
			return false;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! ProductMeta::is_online_invitation( $product ) ) {
			return $passed;
		}

		if ( ProductMeta::is_builder_optional( $product ) ) {
			return $passed;
		}

		$posted_size   = sanitize_text_field( wp_unslash( (string) ( $_POST['attribute_pa_bpp_size'] ?? '' ) ) );
		$posted_format = sanitize_text_field( wp_unslash( (string) ( $_POST['attribute_pa_bpp_format'] ?? '' ) ) );
		$attributes    = BppAttributeDefaults::normalize_posted_attributes( (int) $product_id, $posted_size, $posted_format );

		if ( is_wp_error( $attributes ) ) {
			wc_add_notice( BppAttributeDefaults::customer_error_message( $attributes ), 'error' );

			return false;
		}

		$errors = $this->validator->validate_posted_payload( (int) $product_id );
		if ( [] === $errors ) {
			return $passed;
		}

		wc_add_notice(
			__( 'Please customise the invitation in the PDF Builder before adding it to your cart.', 'prikogstreg-online-invitations' ),
			'error'
		);

		return false;
	}

	/**
	 * @param array<string, mixed> $cart_item_data
	 * @return array<string, mixed>
	 */
	public function annotate_invitation_line( array $cart_item_data, int $product_id, int $variation_id ): array {
		$product = wc_get_product( $product_id );
		if ( ! $product || ! ProductMeta::is_online_invitation( $product ) ) {
			return $cart_item_data;
		}

		$state = $this->validator->build_state_from_request( $product_id );
		$cart_item_data = CartPayload::annotate( $cart_item_data );
		$cart_item_data[ CartPayload::CHECKSUM_KEY ] = $this->validator->compute_checksum( $state );

		return $cart_item_data;
	}

	/**
	 * @param array<string, mixed> $cart_item
	 * @return array<string, mixed>
	 */
	public function restore_from_session( array $cart_item, array $values ): array {
		foreach ( [ CartPayload::MARKER_KEY, CartPayload::VERSION_KEY, CartPayload::CHECKSUM_KEY ] as $key ) {
			if ( isset( $values[ $key ] ) ) {
				$cart_item[ $key ] = $values[ $key ];
			}
		}

		return $cart_item;
	}

	public static function cart_contains_invitation( $cart = null ): bool {
		if ( null === $cart && function_exists( 'WC' ) && WC()->cart ) {
			$cart = WC()->cart;
		}

		if ( ! is_object( $cart ) || ! method_exists( $cart, 'get_cart' ) ) {
			return false;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( CartPayload::is_invitation_line( $cart_item ) ) {
				return true;
			}
		}

		return false;
	}
}
