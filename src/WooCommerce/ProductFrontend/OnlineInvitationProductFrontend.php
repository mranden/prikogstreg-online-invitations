<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\ProductFrontend;

use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;

/**
 * Plugin-owned storefront for online_invitation add-to-cart.
 */
final class OnlineInvitationProductFrontend {

	private static bool $purchase_form_rendered = false;

	public function __construct(
		private ProductReadiness $readiness,
		private EnvelopeFrontend $envelope,
		private BuilderFrontendBridge $builder_bridge,
		private ProductFrontendAssets $assets
	) {}

	public function register(): void {
		add_action( 'woocommerce_online_invitation_add_to_cart', [ $this, 'render_add_to_cart' ], 10, 0 );
		$this->assets->register();
	}

	public function render_add_to_cart(): void {
		global $product;

		if ( ! $product || ! ProductMeta::is_online_invitation( $product ) ) {
			return;
		}

		if ( ! $product->is_visible() ) {
			return;
		}

		if ( self::$purchase_form_rendered ) {
			return;
		}

		$this->readiness->render( $product );

		if ( ! $product->is_purchasable() ) {
			return;
		}

		echo wc_get_stock_html( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WC core helper.

		if ( ! $product->is_in_stock() ) {
			return;
		}

		$template = $this->locate_template();
		if ( '' === $template || ! is_readable( $template ) ) {
			return;
		}

		self::$purchase_form_rendered = true;

		/**
		 * @var \WC_Product $product
		 * @var self $pks_oi_product_frontend
		 */
		$pks_oi_product_frontend = $this;

		include $template;
	}

	public function render_envelope_section( object $product ): void {
		$this->envelope->render( $product );
	}

	public function render_canvas_hint( object $product ): void {
		if ( ! $this->builder_bridge->expects_theme_canvas() || ! $this->builder_bridge->is_customizable_product( $product ) ) {
			return;
		}

		printf(
			'<section class="pks-oi-product-configurator__section pks-oi-product-configurator__canvas-hint" aria-labelledby="pks-oi-canvas-heading" data-pks-oi-section="canvas-hint">'
		);
		printf(
			'<h2 class="pks-oi-sr-only" id="pks-oi-canvas-heading">%s</h2>',
			esc_html__( 'Invitation design canvas', 'prikogstreg-online-invitations' )
		);
		printf(
			'<p class="pks-oi-product-configurator__canvas-hint">%s</p>',
			esc_html__( 'Use the design preview beside this panel to see your invitation update as you edit the fields below.', 'prikogstreg-online-invitations' )
		);
		echo '</section>';
	}

	public function render_builder_fields( object $product ): void {
		$this->builder_bridge->render_builder_fields( $product );
	}

	public function render_future_options( object $product ): void {
		if ( ! has_action( 'pks_oi/product_purchase_options' ) ) {
			return;
		}

		echo '<section class="pks-oi-product-configurator__section pks-oi-product-configurator__options" aria-labelledby="pks-oi-options-heading" data-pks-oi-section="product-options">';
		printf(
			'<h2 class="pks-oi-product-configurator__section-title" id="pks-oi-options-heading">%s</h2>',
			esc_html__( 'Invitation options', 'prikogstreg-online-invitations' )
		);
		/**
		 * Reserved slot for future invitation-specific purchase options.
		 */
		do_action( 'pks_oi/product_purchase_options', $product );
		echo '</section>';
	}

	public function uses_bpp_purchase_button( object $product ): bool {
		return $this->builder_bridge->uses_bpp_purchase_button( $product );
	}

	public function render_native_purchase_button( object $product ): void {
		if ( $this->uses_bpp_purchase_button( $product ) ) {
			return;
		}

		$button_class = 'single_add_to_cart_button button alt';
		$theme_class  = function_exists( 'wc_wp_theme_get_element_class_name' )
			? wc_wp_theme_get_element_class_name( 'button' )
			: '';
		if ( is_string( $theme_class ) && '' !== $theme_class ) {
			$button_class .= ' ' . $theme_class;
		}

		printf(
			'<button type="submit" name="add-to-cart" value="%1$d" class="%2$s" data-pks-oi-section="purchase">%3$s</button>',
			(int) $product->get_id(),
			esc_attr( $button_class ),
			esc_html( $product->single_add_to_cart_text() )
		);
	}

	public function locate_template( ?object $product = null ): string {
		if ( null === $product ) {
			global $product;
		}

		$relative = 'product/add-to-cart-online-invitation.php';
		$theme_candidates = [
			'prikogstreg-online-invitations/' . $relative,
			'woocommerce/single-product/add-to-cart/online-invitation.php',
		];

		if ( function_exists( 'locate_template' ) ) {
			$theme_path = locate_template( $theme_candidates );
			if ( is_string( $theme_path ) && '' !== $theme_path ) {
				/**
				 * @var string $theme_path
				 */
				$theme_path = apply_filters( 'pks_oi/product_add_to_cart_template', $theme_path, $product );
				return is_string( $theme_path ) ? $theme_path : '';
			}
		}

		$plugin_path = PKS_OI_PLUGIN_PATH . 'templates/' . $relative;
		if ( ! is_readable( $plugin_path ) ) {
			return '';
		}

		/**
		 * @var string $plugin_path
		 */
		$plugin_path = apply_filters( 'pks_oi/product_add_to_cart_template', $plugin_path, $product );

		return is_string( $plugin_path ) ? $plugin_path : '';
	}

	/**
	 * Test helper to reset render guard between assertions.
	 */
	public static function reset_render_state_for_tests(): void {
		self::$purchase_form_rendered = false;
		BuilderFrontendBridge::reset_render_state_for_tests();
	}
}
