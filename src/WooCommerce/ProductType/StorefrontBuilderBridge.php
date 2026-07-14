<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\ProductType;

/**
 * Renders the PDF Builder field form on simple online_invitation product pages.
 */
final class StorefrontBuilderBridge {

	private static bool $field_form_rendered = false;

	public function register(): void {
		add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'render_before_add_to_cart_button' ], 10 );
	}

	public function render_before_add_to_cart_button(): void {
		if ( self::$field_form_rendered || did_action( 'woocommerce_bpp_options' ) > 0 ) {
			return;
		}

		if ( ! $this->should_render_builder_form() ) {
			return;
		}

		$product_id = (int) get_the_ID();
		$defaults     = BppAttributeDefaults::resolve( $product_id );
		if ( is_wp_error( $defaults ) ) {
			return;
		}

		if ( ! $this->form_already_provides_bpp_attributes() ) {
			$this->render_hidden_attribute_inputs( $defaults );
		}

		do_action( 'woocommerce_bpp_options' );
		self::$field_form_rendered = true;
	}

	public function should_render_builder_form( $product = null ): bool {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return false;
		}

		if ( null === $product ) {
			$product = wc_get_product();
		}

		if ( ! $product || ! ProductMeta::is_online_invitation( $product ) ) {
			return false;
		}

		if ( ProductMeta::is_builder_optional( $product ) ) {
			return false;
		}

		if ( ! class_exists( 'BPP_Product', false ) ) {
			return false;
		}

		$product_id = (int) $product->get_id();
		if ( ! (bool) apply_filters( 'bpp/is_product_customizable', false, $product_id ) ) {
			return false;
		}

		if ( $product->is_type( 'variable' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param array{size:string,format:string} $defaults
	 */
	private function render_hidden_attribute_inputs( array $defaults ): void {
		printf(
			'<input type="hidden" name="attribute_pa_bpp_size" value="%s" />',
			esc_attr( $defaults['size'] )
		);
		printf(
			'<input type="hidden" name="attribute_pa_bpp_format" value="%s" />',
			esc_attr( $defaults['format'] )
		);
	}

	private function form_already_provides_bpp_attributes(): bool {
		$product = wc_get_product();

		return $product && $product->is_type( 'variable' );
	}

	/**
	 * Test helper to reset render guard between assertions.
	 */
	public static function reset_render_state_for_tests(): void {
		self::$field_form_rendered = false;
	}
}
