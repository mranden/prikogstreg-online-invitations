<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\WooCommerce;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductFrontend\ProductFrontendAssets;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class ProductFrontendAssetsTest extends TestCase {

	public function test_frontend_assets_load_only_on_relevant_products(): void {
		$assets  = new ProductFrontendAssets();
		$product = $this->make_product( ProductMeta::TYPE );

		$this->assertTrue( $assets->should_enqueue_for_product( $product ) );

		$source = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/src/WooCommerce/ProductFrontend/ProductFrontendAssets.php'
		);

		$this->assertStringContainsString( "'pks-oi-product'", $source );
		$this->assertStringContainsString( 'assets/build/css/product.css', $source );
		$this->assertStringContainsString( 'assets/build/js/product.js', $source );
		$this->assertStringNotContainsString( 'assets/build/css/public.css', $source );
	}

	public function test_normal_products_do_not_receive_oi_assets(): void {
		$assets = new ProductFrontendAssets();

		$this->assertFalse( $assets->should_enqueue_for_product( $this->make_product( 'simple' ) ) );
		$this->assertFalse( $assets->should_enqueue_for_product( null ) );
	}

	public function test_maybe_enqueue_skips_non_product_pages(): void {
		Functions\when( 'is_product' )->justReturn( false );

		$assets = new ProductFrontendAssets();
		$assets->maybe_enqueue();

		$this->assertTrue( true );
	}

	/**
	 * @return object
	 */
	private function make_product( string $type ): object {
		return new class( $type ) {
			public function __construct( private string $type ) {}

			public function is_type( string $type ): bool {
				return $this->type === $type;
			}
		};
	}
}
