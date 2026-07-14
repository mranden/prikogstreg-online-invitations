<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\WooCommerce;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductFrontend\BuilderFrontendBridge;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductFrontend\EnvelopeFrontend;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductFrontend\OnlineInvitationProductFrontend;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductFrontend\ProductFrontendAssets;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductFrontend\ProductReadiness;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class OnlineInvitationProductFrontendTest extends TestCase {

	private string $plugin_root;

	protected function setUp(): void {
		parent::setUp();
		$this->plugin_root = dirname( __DIR__, 3 );
		OnlineInvitationProductFrontend::reset_render_state_for_tests();
		\Brain\Monkey\Functions\when( 'has_action' )->justReturn( 1 );
		\Brain\Monkey\Functions\when( 'current_user_can' )->justReturn( false );
	}

	protected function tearDown(): void {
		OnlineInvitationProductFrontend::reset_render_state_for_tests();
		\BPP_Product::reset_test_models();
		parent::tearDown();
	}

	public function test_registers_custom_add_to_cart_handler_once(): void {
		$source = (string) file_get_contents(
			$this->plugin_root . '/src/WooCommerce/ProductType/ProductTypeRegistrar.php'
		);

		$this->assertStringContainsString( 'OnlineInvitationProductFrontend', $source );
		$this->assertStringNotContainsString(
			"add_action( 'woocommerce_online_invitation_add_to_cart', 'woocommerce_simple_add_to_cart' )",
			$source
		);

		$frontend_source = (string) file_get_contents(
			$this->plugin_root . '/src/WooCommerce/ProductFrontend/OnlineInvitationProductFrontend.php'
		);
		$this->assertStringContainsString(
			"add_action( 'woocommerce_online_invitation_add_to_cart', [ \$this, 'render_add_to_cart' ], 10, 0 )",
			$frontend_source
		);
	}

	public function test_locate_template_prefers_plugin_template(): void {
		$frontend = $this->make_frontend();
		$path     = $frontend->locate_template( $this->make_product( 10, true, true, true ) );

		$this->assertStringEndsWith( 'templates/product/add-to-cart-online-invitation.php', $path );
	}

	public function test_template_uses_multipart_form_and_fixed_quantity(): void {
		$template = (string) file_get_contents(
			$this->plugin_root . '/templates/product/add-to-cart-online-invitation.php'
		);

		$this->assertStringContainsString( 'enctype="multipart/form-data"', $template );
		$this->assertStringContainsString( 'pks-oi-product-configurator', $template );
		$this->assertStringContainsString( 'name="quantity" value="1"', $template );
		$this->assertStringNotContainsString( 'woocommerce_quantity_input', $template );
	}

	public function test_template_preserves_add_to_cart_hook_order(): void {
		$template = (string) file_get_contents(
			$this->plugin_root . '/templates/product/add-to-cart-online-invitation.php'
		);

		$hooks = [
			'woocommerce_before_add_to_cart_form',
			'woocommerce_before_add_to_cart_button',
			'woocommerce_before_add_to_cart_quantity',
			'woocommerce_after_add_to_cart_quantity',
			'woocommerce_after_add_to_cart_button',
			'woocommerce_after_add_to_cart_form',
		];

		$last_pos = -1;
		foreach ( $hooks as $hook ) {
			$pos = strpos( $template, $hook );
			$this->assertNotFalse( $pos, 'Missing hook: ' . $hook );
			$this->assertGreaterThan( $last_pos, (int) $pos, 'Hook out of order: ' . $hook );
			$last_pos = (int) $pos;
		}
	}

	public function test_template_omits_native_button_when_bpp_purchase_is_used(): void {
		$template = (string) file_get_contents(
			$this->plugin_root . '/templates/product/add-to-cart-online-invitation.php'
		);

		$this->assertStringContainsString( 'render_native_purchase_button', $template );
		$this->assertStringNotContainsString( 'wc_bpp_cart_style', $template );
		$this->assertSame( 1, substr_count( $template, '<form' ) );
	}

	public function test_render_native_purchase_button_skipped_for_customizable_products(): void {
		$frontend = $this->make_frontend();
		$product  = $this->make_product( 10, true, true, true );

		Functions\when( 'is_product' )->justReturn( true );
		Functions\when( 'wc_get_product' )->alias(
			static function ( int $product_id ) use ( $product ) {
				return 10 === $product_id ? $product : null;
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				if ( 'bpp/is_product_customizable' === $hook ) {
					return true;
				}

				return $value;
			}
		);

		ob_start();
		$frontend->render_native_purchase_button( $product );
		$output = (string) ob_get_clean();

		$this->assertSame( '', $output );
	}

	public function test_render_native_purchase_button_outputs_submit_for_non_customizable_products(): void {
		$frontend = $this->make_frontend();
		$product  = $this->make_product( 10, true, true, true, true );

		Functions\when( 'is_product' )->justReturn( true );
		Functions\when( 'wc_wp_theme_get_element_class_name' )->justReturn( '' );

		ob_start();
		$frontend->render_native_purchase_button( $product );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'type="submit"', $output );
		$this->assertStringContainsString( 'name="add-to-cart"', $output );
		$this->assertStringContainsString( 'value="10"', $output );
		$this->assertStringContainsString( 'single_add_to_cart_button', $output );
	}

	public function test_unavailable_product_does_not_render_purchase_form(): void {
		global $product;
		$product  = $this->make_product( 10, false, true, false );
		$frontend = $this->make_frontend();

		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\when( 'wc_get_stock_html' )->justReturn( '' );

		ob_start();
		$frontend->render_add_to_cart();
		$output = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'pks-oi-add-to-cart', $output );
		$this->assertStringNotContainsString( '<form', $output );
	}

	public function test_out_of_stock_product_does_not_render_purchase_form(): void {
		global $product;
		$product  = $this->make_product( 10, true, true, false );
		$frontend = $this->make_frontend();

		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\when( 'wc_get_stock_html' )->justReturn( '<p class="stock">Out of stock</p>' );
		Functions\when( 'get_post_meta' )->alias(
			static function ( int $post_id, string $key, bool $single ) {
				return match ( $key ) {
					ProductMeta::ENVELOPE_PRESET => 'classic',
					ProductMeta::BACKGROUND_PRESET => 'neutral',
					default => '',
				};
			}
		);

		ob_start();
		$frontend->render_add_to_cart();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'stock', $output );
		$this->assertStringNotContainsString( 'pks-oi-add-to-cart', $output );
	}

	public function test_render_add_to_cart_outputs_single_form_with_expected_sections(): void {
		global $product;
		$product  = $this->make_product( 10, true, true, true, false );
		$frontend = $this->make_frontend();

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
				'pages'           => [
					(object) [ 'low_res_html' => '<div>page</div>' ],
				],
			]
		);

		Functions\when( 'wc_get_product' )->alias(
			static function ( int $product_id ) use ( $product ) {
				return 10 === $product_id ? $product : null;
			}
		);
		Functions\when( 'wc_get_stock_html' )->justReturn( '' );
		Functions\when( 'is_product' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value, ...$args ) {
				if ( 'woocommerce_add_to_cart_form_action' === $hook ) {
					return 'https://example.test/product/invitation/';
				}
				if ( 'bpp/is_product_customizable' === $hook ) {
					return true;
				}
				if ( 'pks_oi/envelope_design' === $hook ) {
					return $value;
				}
				if ( 'pks_oi/product_readiness_messages' === $hook ) {
					return $value;
				}

				return $value;
			}
		);
		Functions\when( 'get_post_meta' )->alias(
			static function ( int $post_id, string $key, bool $single ) {
				return match ( $key ) {
					ProductMeta::ENVELOPE_PRESET => 'classic',
					ProductMeta::BACKGROUND_PRESET => 'neutral',
					default => '',
				};
			}
		);
		Functions\when( 'wc_wp_theme_get_element_class_name' )->justReturn( '' );
		Functions\when( 'do_action' )->alias(
			static function ( string $hook ): void {
				if ( 'woocommerce_bpp_options' === $hook ) {
					echo '<div class="product-addons-for-customizer">fields</div>';
				}
			}
		);

		ob_start();
		$frontend->render_add_to_cart();
		$output = (string) ob_get_clean();

		$this->assertSame( 1, substr_count( $output, '<form' ) );
		$this->assertStringContainsString( 'pks-oi-product-configurator', $output );
		$this->assertStringContainsString( 'enctype="multipart/form-data"', $output );
		$this->assertStringContainsString( 'name="quantity" value="1"', $output );
		$this->assertStringContainsString( 'data-pks-oi-section="envelope"', $output );
		$this->assertStringContainsString( 'data-pks-oi-section="builder-fields"', $output );
		$this->assertStringContainsString( 'name="attribute_pa_bpp_size"', $output );
		$this->assertStringContainsString( 'product-addons-for-customizer', $output );
		$this->assertStringNotContainsString( 'name="add-to-cart"', $output );
	}

	public function test_render_add_to_cart_is_not_duplicated(): void {
		global $product;
		$product  = $this->make_product( 10, true, true, true, true );
		$frontend = $this->make_frontend();

		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\when( 'wc_get_stock_html' )->justReturn( '' );
		Functions\when( 'is_product' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				if ( 'woocommerce_add_to_cart_form_action' === $hook ) {
					return 'https://example.test/product/invitation/';
				}

				return $value;
			}
		);
		Functions\when( 'get_post_meta' )->alias(
			static function ( int $post_id, string $key, bool $single ) {
				return match ( $key ) {
					ProductMeta::ENVELOPE_PRESET => 'classic',
					ProductMeta::BACKGROUND_PRESET => 'neutral',
					default => '',
				};
			}
		);
		Functions\when( 'wc_wp_theme_get_element_class_name' )->justReturn( '' );
		Functions\when( 'do_action' )->justReturn( null );

		ob_start();
		$frontend->render_add_to_cart();
		$frontend->render_add_to_cart();
		$output = (string) ob_get_clean();

		$this->assertSame( 1, substr_count( $output, '<form' ) );
	}

	public function test_product_frontend_registers_assets_hook(): void {
		$source = (string) file_get_contents(
			$this->plugin_root . '/src/WooCommerce/ProductFrontend/ProductFrontendAssets.php'
		);

		$this->assertStringContainsString(
			"add_action( 'wp_enqueue_scripts', [ \$this, 'maybe_enqueue' ], 20 )",
			$source
		);
		$this->assertStringContainsString( "'pks-oi-product'", $source );
		$this->assertStringContainsString( 'assets/build/css/product.css', $source );
	}

	private function make_frontend(): OnlineInvitationProductFrontend {
		$bridge = new BuilderFrontendBridge( new BuilderService() );

		return new OnlineInvitationProductFrontend(
			new \PrikOgStreg\OnlineInvitations\WooCommerce\ProductFrontend\ProductReadiness( $bridge ),
			new EnvelopeFrontend(),
			$bridge,
			new ProductFrontendAssets()
		);
	}

	/**
	 * @return object
	 */
	private function make_product(
		int $id,
		bool $purchasable,
		bool $visible,
		bool $in_stock,
		bool $builder_optional = false
	): object {
		return new class( $id, $purchasable, $visible, $in_stock, $builder_optional ) {
			public function __construct(
				private int $id,
				private bool $purchasable,
				private bool $visible,
				private bool $in_stock,
				private bool $builder_optional
			) {}

			public function get_id(): int {
				return $this->id;
			}

			public function is_type( string $type ): bool {
				return ProductMeta::TYPE === $type;
			}

			public function is_visible(): bool {
				return $this->visible;
			}

			public function is_purchasable(): bool {
				return $this->purchasable;
			}

			public function is_in_stock(): bool {
				return $this->in_stock;
			}

			public function get_permalink(): string {
				return 'https://example.test/product/invitation/';
			}

			public function single_add_to_cart_text(): string {
				return 'Add to cart';
			}

			public function get_meta( string $key, bool $single = true ): mixed {
				if ( ProductMeta::BUILDER_OPTIONAL === $key ) {
					return $this->builder_optional ? 'yes' : '';
				}

				return match ( $key ) {
					ProductMeta::ENVELOPE_PRESET => 'classic',
					ProductMeta::BACKGROUND_PRESET => 'neutral',
					default => '',
				};
			}

			public function get_price(): string {
				return $this->purchasable ? '199' : '';
			}

			public function get_gallery_image_ids(): array {
				return [];
			}
		};
	}
}
