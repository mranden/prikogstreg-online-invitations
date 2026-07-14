<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\WooCommerce;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductTypeRegistrar;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\QuantityGuard;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class ProductTypeTest extends TestCase {

	public function test_registers_online_invitation_in_product_type_selector(): void {
		$registrar = new ProductTypeRegistrar();
		$types     = $registrar->add_product_type( [ 'simple' => 'Simple product' ] );

		$this->assertArrayHasKey( 'online_invitation', $types );
		$this->assertSame( 'online_invitation', ProductMeta::TYPE );
	}

	public function test_maps_product_class_to_wc_product_online_invitation(): void {
		$registrar = new ProductTypeRegistrar();
		$class     = $registrar->map_product_class( 'WC_Product_Simple', 'online_invitation' );

		$this->assertSame( 'WC_Product_Online_Invitation', $class );
	}

	public function test_quantity_guard_rejects_non_one_add_to_cart_quantity(): void {
		$invitation = $this->make_product( 10, ProductMeta::TYPE, true );
		$physical   = $this->make_product( 20, 'simple', true );

		Functions\when( 'wc_get_product' )->alias(
			static function ( int $product_id ) use ( $invitation, $physical ) {
				return match ( $product_id ) {
					10 => $invitation,
					20 => $physical,
					default => null,
				};
			}
		);

		Functions\expect( 'wc_add_notice' )->once();

		$guard = new QuantityGuard();
		$this->assertFalse( $guard->validate_add_to_cart( true, 10, 2 ) );
	}

	public function test_quantity_guard_allows_mixed_cart_simple_product_quantity(): void {
		$physical = $this->make_product( 20, 'simple', true );

		Functions\when( 'wc_get_product' )->justReturn( $physical );

		$guard = new QuantityGuard();
		$this->assertTrue( $guard->validate_add_to_cart( true, 20, 3 ) );
	}

	public function test_quantity_guard_blocks_purchase_when_builder_configuration_missing(): void {
		$invitation = $this->make_product( 10, ProductMeta::TYPE, true );

		Functions\when( 'wc_get_product' )->justReturn( $invitation );
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\expect( 'wc_add_notice' )->once();

		$guard = new QuantityGuard();
		$this->assertFalse( $guard->validate_add_to_cart( true, 10, 1 ) );
	}

	public function test_store_api_limits_force_quantity_one(): void {
		$invitation = $this->make_product( 10, ProductMeta::TYPE, true );
		$guard      = new QuantityGuard();
		$limits     = $guard->store_api_limits( [ 'minimum' => 1, 'maximum' => 99, 'editable' => true ], $invitation );

		$this->assertSame( 1, $limits['maximum'] );
		$this->assertFalse( $limits['editable'] );
	}

	public function test_product_data_tabs_only_extend_existing_show_if_classes(): void {
		$registrar = new ProductTypeRegistrar();
		$tabs      = $registrar->product_data_tabs(
			[
				'general'   => [ 'class' => [ 'hide_if_grouped' ] ],
				'inventory' => [ 'class' => [ 'show_if_simple', 'show_if_variable' ] ],
			]
		);

		$this->assertNotContains( 'show_if_online_invitation', $tabs['general']['class'] );
		$this->assertContains( 'show_if_online_invitation', $tabs['inventory']['class'] );
	}

	public function test_registers_online_invitation_add_to_cart_handler(): void {
		$source = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/src/WooCommerce/ProductType/ProductTypeRegistrar.php'
		);

		$this->assertStringContainsString(
			"add_action( 'woocommerce_online_invitation_add_to_cart', 'woocommerce_simple_add_to_cart' )",
			$source
		);
	}

	public function test_bpp_customizable_filter_for_online_invitation_with_active_builder(): void {
		$integration = new \PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\BuilderIntegration();
		$product     = $this->make_product( 10, ProductMeta::TYPE, true );

		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\when( 'get_post_meta' )->justReturn( (object) [ 'active' => true ] );

		$this->assertTrue( $integration->filter_product_customizable( false, 10 ) );
	}

	public function test_bpp_customizable_filter_false_when_builder_optional(): void {
		$integration = new \PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\BuilderIntegration();
		$product     = $this->make_product( 10, ProductMeta::TYPE, true, true );

		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\when( 'get_post_meta' )->justReturn( (object) [ 'active' => true ] );

		$this->assertFalse( $integration->filter_product_customizable( false, 10 ) );
	}

	public function test_quantity_guard_allows_purchase_when_builder_optional(): void {
		$invitation = $this->make_product( 10, ProductMeta::TYPE, true, true );

		Functions\when( 'wc_get_product' )->justReturn( $invitation );
		Functions\when( 'get_post_meta' )->alias(
			static function ( int $post_id, string $key, bool $single ) {
				return match ( $key ) {
					ProductMeta::ENVELOPE_PRESET => 'classic',
					ProductMeta::BACKGROUND_PRESET => 'neutral',
					default => '',
				};
			}
		);

		$guard = new QuantityGuard();
		$this->assertTrue( $guard->validate_add_to_cart( true, 10, 1 ) );
	}

	private function make_product( int $id, string $type, bool $valid_config, bool $builder_optional = false ): object {
		return new class( $id, $type, $valid_config, $builder_optional ) {
			public function __construct(
				private int $id,
				private string $type,
				private bool $valid_config,
				private bool $builder_optional
			) {}

			public function get_id(): int {
				return $this->id;
			}

			public function is_type( string $type ): bool {
				return $this->type === $type;
			}

			public function get_meta( string $key, bool $single = true ): mixed {
				if ( ProductMeta::BUILDER_OPTIONAL === $key ) {
					return $this->builder_optional ? 'yes' : '';
				}

				if ( ! $this->valid_config ) {
					return '';
				}

				return match ( $key ) {
					ProductMeta::ENVELOPE_PRESET => 'classic',
					ProductMeta::BACKGROUND_PRESET => 'neutral',
					default => '',
				};
			}
		};
	}
}
