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
		add_action( 'woocommerce_online_invitation_add_to_cart', 'woocommerce_simple_add_to_cart' );

		require_once PKS_OI_PLUGIN_PATH . 'src/WooCommerce/ProductType/WC_Product_Online_Invitation.php';

		( new ProductDataPanel() )->register();
		( new QuantityGuard() )->register();
		( new BuilderIntegration() )->register();
		( new StorefrontBuilderBridge() )->register();
		( new ProductPagePlaceholder() )->register();
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
		$invitation_class = 'show_if_' . ProductMeta::TYPE;

		foreach ( $tabs as $key => $tab ) {
			$classes = (array) ( $tab['class'] ?? [] );
			$show_keys = [ 'show_if_simple', 'show_if_variable', 'show_if_grouped', 'show_if_external' ];

			if ( [] === array_intersect( $show_keys, $classes ) ) {
				continue;
			}

			$classes[]               = $invitation_class;
			$tabs[ $key ]['class'] = array_values( array_unique( $classes ) );
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
}
