<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\WooCommerce;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\StorefrontBuilderBridge;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class StorefrontBuilderBridgeTest extends TestCase {

	protected function tearDown(): void {
		StorefrontBuilderBridge::reset_render_state_for_tests();
		\BPP_Product::reset_test_models();
		parent::tearDown();
	}

	public function test_registers_before_add_to_cart_button_hook(): void {
		$source = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/src/WooCommerce/ProductType/StorefrontBuilderBridge.php'
		);

		$this->assertStringContainsString(
			"add_action( 'woocommerce_before_add_to_cart_button', [ \$this, 'render_before_add_to_cart_button' ], 10 )",
			$source
		);
	}

	public function test_should_render_for_customizable_online_invitation(): void {
		$bridge  = new StorefrontBuilderBridge();
		$product = $this->make_product( 10, ProductMeta::TYPE, false );

		Functions\when( 'is_product' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value, ...$args ) {
				if ( 'bpp/is_product_customizable' === $hook ) {
					return true;
				}

				return $value;
			}
		);

		$this->assertTrue( $bridge->should_render_builder_form( $product ) );
	}

	public function test_should_not_render_for_non_customizable_online_invitation(): void {
		$bridge  = new StorefrontBuilderBridge();
		$product = $this->make_product( 10, ProductMeta::TYPE, false );

		Functions\when( 'is_product' )->justReturn( true );
		Functions\when( 'apply_filters' )->justReturn( false );

		$this->assertFalse( $bridge->should_render_builder_form( $product ) );
	}

	public function test_should_not_render_for_regular_simple_product(): void {
		$bridge  = new StorefrontBuilderBridge();
		$product = $this->make_product( 20, 'simple', false );

		Functions\when( 'is_product' )->justReturn( true );

		$this->assertFalse( $bridge->should_render_builder_form( $product ) );
	}

	public function test_should_not_render_for_builder_optional_product(): void {
		$bridge  = new StorefrontBuilderBridge();
		$product = $this->make_product( 10, ProductMeta::TYPE, true );

		Functions\when( 'is_product' )->justReturn( true );

		$this->assertFalse( $bridge->should_render_builder_form( $product ) );
	}

	public function test_should_not_render_for_variable_product(): void {
		$bridge  = new StorefrontBuilderBridge();
		$product = $this->make_product( 30, 'variable', false );

		Functions\when( 'is_product' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				if ( 'bpp/is_product_customizable' === $hook ) {
					return true;
				}

				return $value;
			}
		);

		$this->assertFalse( $bridge->should_render_builder_form( $product ) );
	}

	public function test_renders_hidden_size_and_format_defaults_and_field_form_once(): void {
		$product = $this->make_product( 10, ProductMeta::TYPE, false );

		Functions\when( 'is_product' )->justReturn( true );
		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\when( 'get_the_ID' )->justReturn( 10 );
		Functions\when( 'did_action' )->justReturn( 0 );
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value, ...$args ) {
				if ( 'bpp/is_product_customizable' === $hook ) {
					return true;
				}

				return $value;
			}
		);

		$field_form_calls = 0;
		Functions\when( 'do_action' )->alias(
			static function ( string $hook ) use ( &$field_form_calls ): void {
				if ( 'woocommerce_bpp_options' === $hook ) {
					++$field_form_calls;
					echo '<div class="product-addons-for-customizer">fields</div>';
				}
			}
		);

		$bridge = new StorefrontBuilderBridge();

		ob_start();
		$bridge->render_before_add_to_cart_button();
		$first_output = (string) ob_get_clean();

		ob_start();
		$bridge->render_before_add_to_cart_button();
		$second_output = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="attribute_pa_bpp_size"', $first_output );
		$this->assertStringContainsString( 'value="a5"', $first_output );
		$this->assertStringContainsString( 'name="attribute_pa_bpp_format"', $first_output );
		$this->assertStringContainsString( 'value="flat"', $first_output );
		$this->assertStringContainsString( 'product-addons-for-customizer', $first_output );
		$this->assertSame( 1, $field_form_calls );
		$this->assertSame( '', $second_output );
	}

	public function test_does_not_render_when_defaults_cannot_be_resolved(): void {
		$product = $this->make_product( 10, ProductMeta::TYPE, false );

		\BPP_Product::set_test_model(
			10,
			(object) [
				'active'          => true,
				'type'            => 'invitation',
				'foldable'        => false,
				'default_size'    => 'a5',
				'available_sizes' => [ 'invitation' => [] ],
			]
		);

		Functions\when( 'is_product' )->justReturn( true );
		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\when( 'get_the_ID' )->justReturn( 10 );
		Functions\when( 'did_action' )->justReturn( 0 );
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				if ( 'bpp/is_product_customizable' === $hook ) {
					return true;
				}

				return $value;
			}
		);
		Functions\when( 'do_action' )->justReturn( null );

		$bridge = new StorefrontBuilderBridge();

		ob_start();
		$bridge->render_before_add_to_cart_button();
		$output = (string) ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * @return object
	 */
	private function make_product( int $id, string $type, bool $builder_optional ): object {
		return new class( $id, $type, $builder_optional ) {
			public function __construct(
				private int $id,
				private string $type,
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

				return '';
			}
		};
	}
}
