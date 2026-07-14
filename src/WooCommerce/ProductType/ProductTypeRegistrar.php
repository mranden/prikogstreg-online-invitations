<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\ProductType;

/**
 * Registers the online_invitation WooCommerce product type.
 */
final class ProductTypeRegistrar {

	public function register(): void {
		add_filter( 'product_type_selector', [ $this, 'add_product_type' ] );
		add_filter( 'woocommerce_product_class', [ $this, 'map_product_class' ], 10, 2 );
		add_filter( 'woocommerce_product_data_tabs', [ $this, 'product_data_tabs' ] );
		add_filter( 'woocommerce_product_supports', [ $this, 'product_supports' ], 10, 3 );

		require_once PKS_OI_PLUGIN_PATH . 'src/WooCommerce/ProductType/WC_Product_Online_Invitation.php';

		( new ProductDataPanel() )->register();
		( new QuantityGuard() )->register();
		( new BuilderIntegration() )->register();

		add_action( 'admin_footer', [ $this, 'admin_product_type_script' ] );
	}

	/**
	 * @param array<string, string> $types
	 * @return array<string, string>
	 */
	public function add_product_type( array $types ): array {
		$types[ ProductMeta::TYPE ] = __( 'Online invitation', 'prikogstreg-online-invitations' );

		return $types;
	}

	public function map_product_class( string $classname, string $product_type ): string {
		if ( ProductMeta::TYPE === $product_type ) {
			return 'WC_Product_Online_Invitation';
		}

		return $classname;
	}

	/**
	 * @param array<string, array<string, mixed>> $tabs
	 * @return array<string, array<string, mixed>>
	 */
	public function product_data_tabs( array $tabs ): array {
		$classes = [
			'show_if_' . ProductMeta::TYPE,
		];

		foreach ( [ 'general', 'inventory', 'shipping', 'linked_product', 'attribute', 'advanced' ] as $tab_key ) {
			if ( isset( $tabs[ $tab_key ]['class'] ) && is_array( $tabs[ $tab_key ]['class'] ) ) {
				$tabs[ $tab_key ]['class'] = array_merge( $tabs[ $tab_key ]['class'], $classes );
			}
		}

		return $tabs;
	}

	public function product_supports( bool $supports, string $feature, $product ): bool {
		if ( ! ProductMeta::is_online_invitation( $product ) ) {
			return $supports;
		}

		if ( in_array( $feature, [ 'ajax_add_to_cart' ], true ) ) {
			return $supports;
		}

		return $supports;
	}

	public function admin_product_type_script(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}
		?>
		<script>
			jQuery(function ($) {
				$('.options_group').each(function () {
					if ($(this).hasClass('show_if_simple') || $(this).hasClass('show_if_virtual')) {
						$(this).addClass('show_if_<?php echo esc_js( ProductMeta::TYPE ); ?>');
					}
				});
				$('#product-type').trigger('change');
			});
		</script>
		<?php
	}
}
