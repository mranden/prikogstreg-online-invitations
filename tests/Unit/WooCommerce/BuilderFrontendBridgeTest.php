<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\WooCommerce;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductFrontend\BuilderFrontendBridge;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class BuilderFrontendBridgeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'has_action' )->justReturn( 1 );
		Functions\when( 'wc_get_product' )->alias(
			function ( int $product_id ) {
				return $this->make_product( $product_id, ProductMeta::TYPE, false );
			}
		);
	}

	protected function tearDown(): void {
		BuilderFrontendBridge::reset_render_state_for_tests();
		\BPP_Product::reset_test_models();
		parent::tearDown();
	}

	public function test_is_builder_plugin_available_when_bpp_product_exists(): void {
		$this->assertTrue( $this->make_bridge()->is_builder_plugin_available() );
	}

	public function test_should_render_for_customizable_online_invitation(): void {
		$bridge  = $this->make_bridge();
		$product = $this->make_product( 10, ProductMeta::TYPE, false );

		Functions\when( 'is_product' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				if ( 'bpp/is_product_customizable' === $hook ) {
					return true;
				}

				return $value;
			}
		);

		$this->assertTrue( $bridge->should_render_builder_fields( $product ) );
	}

	public function test_should_not_render_for_inactive_design(): void {
		\BPP_Product::set_test_model(
			10,
			(object) [
				'active'          => false,
				'type'            => 'invitation',
				'foldable'        => false,
				'default_size'    => 'a5',
				'available_sizes' => [ 'invitation' => [] ],
				'pages'           => [],
			]
		);

		$bridge  = $this->make_bridge();
		$product = $this->make_product( 10, ProductMeta::TYPE, false );

		Functions\when( 'is_product' )->justReturn( true );
		Functions\when( 'apply_filters' )->justReturn( false );

		$this->assertFalse( $bridge->should_render_builder_fields( $product ) );
	}

	public function test_should_not_render_when_pdf_plugin_hook_missing(): void {
		Functions\when( 'has_action' )->justReturn( 0 );

		$bridge  = $this->make_bridge();
		$product = $this->make_product( 10, ProductMeta::TYPE, false );

		Functions\when( 'is_product' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				if ( 'bpp/is_product_customizable' === $hook ) {
					return true;
				}

				return $value;
			}
		);

		$this->assertFalse( $bridge->should_render_builder_fields( $product ) );
	}

	public function test_should_not_render_for_regular_simple_product(): void {
		$bridge  = $this->make_bridge();
		$product = $this->make_product( 20, 'simple', false );

		Functions\when( 'is_product' )->justReturn( true );

		$this->assertFalse( $bridge->should_render_builder_fields( $product ) );
	}

	public function test_should_not_render_for_builder_optional_product(): void {
		$bridge  = $this->make_bridge();
		$product = $this->make_product( 10, ProductMeta::TYPE, true );

		Functions\when( 'is_product' )->justReturn( true );

		$this->assertFalse( $bridge->should_render_builder_fields( $product ) );
	}

	public function test_renders_hidden_size_and_format_defaults_and_field_form_once(): void {
		$product = $this->make_product( 10, ProductMeta::TYPE, false );

		Functions\when( 'is_product' )->justReturn( true );
		Functions\when( 'did_action' )->justReturn( 0 );
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				if ( 'bpp/is_product_customizable' === $hook ) {
					return true;
				}

				return $value;
			}
		);

		$field_form_calls = 0;
		Functions\when( 'do_action' )->alias(
			static function ( string $hook ) use ( &$field_form_calls ): void {
				if ( BuilderFrontendBridge::FIELD_FORM_ACTION === $hook ) {
					++$field_form_calls;
					echo '<div class="product-addons-for-customizer">fields</div>';
				}
			}
		);

		$bridge = $this->make_bridge();

		ob_start();
		$bridge->render_builder_fields( $product );
		$first_output = (string) ob_get_clean();

		ob_start();
		$bridge->render_builder_fields( $product );
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
				'pages'           => [
					(object) [ 'low_res_html' => '<div>page</div>' ],
				],
			]
		);

		Functions\when( 'is_product' )->justReturn( true );
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

		$bridge = $this->make_bridge();

		ob_start();
		$bridge->render_builder_fields( $product );
		$output = (string) ob_get_clean();

		$this->assertSame( '', $output );
	}

	public function test_builder_readiness_reports_missing_pages(): void {
		\BPP_Product::set_test_model(
			10,
			(object) [
				'active'          => true,
				'type'            => 'invitation',
				'foldable'        => false,
				'default_size'    => 'a5',
				'available_sizes' => [
					'invitation' => [
						[
							'attribute_slug' => 'a5',
							'available'      => true,
						],
					],
				],
				'pages'           => [],
			]
		);

		$errors = $this->make_bridge()->builder_readiness_errors( 10 );

		$this->assertContains( 'builder_pages_missing', $errors );
	}

	public function test_normalize_posted_attributes_rejects_tampered_size(): void {
		$result = $this->make_bridge()->normalize_posted_attributes( 10, 'giant', 'flat' );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'bpp_size_not_permitted', $result->get_error_code() );
	}

	public function test_add_to_cart_template_does_not_duplicate_canvas_markup(): void {
		$template = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/templates/product/add-to-cart-online-invitation.php'
		);

		$this->assertStringNotContainsString( 'customizer-area', $template );
		$this->assertStringNotContainsString( 'working_div', $template );
		$this->assertStringNotContainsString( 'content_single_product', $template );
	}

	public function test_expects_theme_canvas_without_rendering_it(): void {
		$this->assertTrue( $this->make_bridge()->expects_theme_canvas() );
	}

	public function test_customer_readiness_message_avoids_internal_identifiers(): void {
		$message = $this->make_bridge()->customer_readiness_message( 'builder_template_missing' );

		$this->assertStringNotContainsString( 'BPP_', $message );
		$this->assertStringNotContainsString( '.php', $message );
	}

	private function make_bridge(): BuilderFrontendBridge {
		return new BuilderFrontendBridge( new BuilderService() );
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
