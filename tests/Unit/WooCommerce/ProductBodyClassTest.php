<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\WooCommerce;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductFrontend\BuilderFrontendBridge;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductFrontend\ProductBodyClass;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class ProductBodyClassTest extends TestCase {

	protected function tearDown(): void {
		\BPP_Product::reset_test_models();
		parent::tearDown();
	}

	public function test_adds_product_workspace_class_for_online_invitation(): void {
		$product = $this->make_product();

		Functions\when( 'is_product' )->justReturn( true );
		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				if ( 'bpp/is_product_customizable' === $hook ) {
					return true;
				}

				return $value;
			}
		);

		\BPP_Product::set_test_model(
			10,
			(object) [
				'active' => true,
				'pages'  => [ (object) [ 'low_res_html' => '<div>page</div>' ] ],
			]
		);

		$body_class = new ProductBodyClass( new BuilderFrontendBridge( new BuilderService() ) );
		$classes    = $body_class->filter_body_class( [] );

		$this->assertContains( 'pks-oi-product-page', $classes );
		$this->assertContains( 'pks-oi-product-workspace', $classes );
		$this->assertContains( 'pks-oi-has-builder-canvas', $classes );
		$this->assertContains( 'pks-oi-product-configurator-active', $classes );
	}

	public function test_normal_product_pages_keep_default_body_classes(): void {
		$simple = new class() {
			public function is_type( string $type ): bool {
				return 'simple' === $type;
			}
		};

		Functions\when( 'is_product' )->justReturn( true );
		Functions\when( 'wc_get_product' )->justReturn( $simple );

		$body_class = new ProductBodyClass( new BuilderFrontendBridge( new BuilderService() ) );
		$classes    = $body_class->filter_body_class( [ 'single-product' ] );

		$this->assertSame( [ 'single-product' ], $classes );
	}

	public function test_duplicate_image_gallery_output_is_avoided_via_body_class(): void {
		$source = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/assets/src/scss/product.scss'
		);

		$this->assertStringContainsString( 'pks-oi-has-builder-canvas', $source );
		$this->assertStringContainsString( '.woocommerce-product-gallery.images', $source );
	}

	/**
	 * @return object
	 */
	private function make_product(): object {
		return new class() {
			public function get_id(): int {
				return 10;
			}

			public function is_type( string $type ): bool {
				return ProductMeta::TYPE === $type;
			}

			public function get_meta( string $key, bool $single = true ): mixed {
				return '';
			}
		};
	}
}
