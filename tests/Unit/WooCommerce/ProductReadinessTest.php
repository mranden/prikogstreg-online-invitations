<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\WooCommerce;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductFrontend\BuilderFrontendBridge;
use PrikOgStreg\OnlineInvitations\Admin\Capabilities;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductFrontend\ProductReadiness;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class ProductReadinessTest extends TestCase {

	protected function tearDown(): void {
		\BPP_Product::reset_test_models();
		parent::tearDown();
	}

	public function test_missing_envelope_creates_readiness_failure(): void {
		$product = $this->make_product( 10, true, '' );

		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\when( 'get_post_meta' )->alias(
			static function ( int $post_id, string $key, bool $single ) {
				return match ( $key ) {
					ProductMeta::BACKGROUND_PRESET => 'neutral',
					default => '',
				};
			}
		);

		$readiness = $this->make_readiness();
		$codes     = $readiness->error_codes_for_product( $product );

		$this->assertContains( 'envelope_preset_missing', $codes );
		$this->assertNotEmpty( $readiness->messages_for_product( $product ) );
	}

	public function test_customer_message_contains_no_internal_details(): void {
		$product = $this->make_product( 10, true, '' );

		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\when( 'get_post_meta' )->alias(
			static function ( int $post_id, string $key, bool $single ) {
				return match ( $key ) {
					ProductMeta::BACKGROUND_PRESET => 'neutral',
					default => '',
				};
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				return $value;
			}
		);
		Functions\when( 'current_user_can' )->justReturn( false );

		$readiness = $this->make_readiness();

		ob_start();
		$readiness->render( $product );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'aria-live="polite"', $output );
		$this->assertStringNotContainsString( 'envelope_preset_missing', $output );
		$this->assertStringNotContainsString( 'Administrator diagnostics', $output );
		$this->assertStringNotContainsString( '<code>', $output );
	}

	public function test_admin_diagnostics_require_capability(): void {
		$product = $this->make_product( 10, true, '' );

		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\when( 'get_post_meta' )->alias(
			static function ( int $post_id, string $key, bool $single ) {
				return match ( $key ) {
					ProductMeta::BACKGROUND_PRESET => 'neutral',
					default => '',
				};
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				return $value;
			}
		);
		Functions\when( 'current_user_can' )->justReturn( false );

		$readiness = $this->make_readiness();

		ob_start();
		$readiness->render( $product );
		$output = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'pks-oi-product-readiness__admin', $output );
	}

	public function test_admin_diagnostics_render_for_shop_managers(): void {
		$product = $this->make_product( 10, true, '' );

		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\when( 'get_post_meta' )->alias(
			static function ( int $post_id, string $key, bool $single ) {
				return match ( $key ) {
					ProductMeta::BACKGROUND_PRESET => 'neutral',
					default => '',
				};
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				return $value;
			}
		);
		Functions\when( 'current_user_can' )->alias(
			static function ( string $cap ): bool {
				return Capabilities::VIEW === $cap;
			}
		);

		$readiness = $this->make_readiness();

		ob_start();
		$readiness->render( $product );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'pks-oi-product-readiness__admin', $output );
		$this->assertStringContainsString( '<code>envelope_preset_missing</code>', $output );
	}

	private function make_readiness(): ProductReadiness {
		return new ProductReadiness( new BuilderFrontendBridge( new BuilderService() ) );
	}

	/**
	 * @return object
	 */
	private function make_product( int $id, bool $purchasable, string $envelope_preset ): object {
		return new class( $id, $purchasable, $envelope_preset ) {
			public function __construct(
				private int $id,
				private bool $purchasable,
				private string $envelope_preset
			) {}

			public function get_id(): int {
				return $this->id;
			}

			public function is_type( string $type ): bool {
				return ProductMeta::TYPE === $type;
			}

			public function is_purchasable(): bool {
				return $this->purchasable;
			}

			public function get_price(): string {
				return $this->purchasable ? '199' : '';
			}

			public function get_meta( string $key, bool $single = true ): mixed {
				return match ( $key ) {
					ProductMeta::ENVELOPE_PRESET => $this->envelope_preset,
					ProductMeta::BACKGROUND_PRESET => 'neutral',
					default => '',
				};
			}
		};
	}
}
