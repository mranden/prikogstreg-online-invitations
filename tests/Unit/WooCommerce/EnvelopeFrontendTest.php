<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\WooCommerce;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductFrontend\EnvelopeFrontend;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class EnvelopeFrontendTest extends TestCase {

	public function test_renders_envelope_preview_from_existing_configuration(): void {
		$product = $this->make_product(
			[
				ProductMeta::ENVELOPE_PRESET   => 'classic',
				ProductMeta::BACKGROUND_PRESET => 'floral',
				ProductMeta::ENVELOPE_IMAGE_ID => 55,
			]
		);

		Functions\when( 'get_post_type' )->justReturn( 'attachment' );
		Functions\when( 'wp_attachment_is_image' )->justReturn( true );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( 'https://example.test/envelope.jpg' );
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				return $value;
			}
		);

		$frontend = new EnvelopeFrontend();

		ob_start();
		$frontend->render( $product );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'data-pks-oi-section="envelope"', $output );
		$this->assertStringContainsString( 'pks-oi-envelope--classic', $output );
		$this->assertStringContainsString( 'pks-oi-envelope--bg-floral', $output );
		$this->assertStringContainsString( 'https://example.test/envelope.jpg', $output );
		$this->assertStringContainsString( 'pks-oi-product-envelope-preview__inner', $output );
		$this->assertStringNotContainsString( '<script', $output );
	}

	public function test_invalid_attachment_id_is_not_rendered_as_image(): void {
		$product = $this->make_product(
			[
				ProductMeta::ENVELOPE_PRESET   => 'classic',
				ProductMeta::BACKGROUND_PRESET => 'neutral',
				ProductMeta::ENVELOPE_IMAGE_ID => 99,
			]
		);

		Functions\when( 'get_post_type' )->justReturn( 'attachment' );
		Functions\when( 'wp_attachment_is_image' )->justReturn( false );
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				return $value;
			}
		);

		$frontend = new EnvelopeFrontend();

		ob_start();
		$frontend->render( $product );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'pks-oi-product-envelope-preview__card-fallback', $output );
		$this->assertStringNotContainsString( '<img', $output );
	}

	public function test_output_is_escaped(): void {
		$product = $this->make_product(
			[
				ProductMeta::ENVELOPE_PRESET   => 'classic"><script',
				ProductMeta::BACKGROUND_PRESET => 'neutral',
			]
		);

		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				return $value;
			}
		);

		$frontend = new EnvelopeFrontend();

		ob_start();
		$frontend->render( $product );
		$output = (string) ob_get_clean();

		$this->assertStringNotContainsString( '<script', $output );
		$this->assertStringNotContainsString( 'classic"><script', $output );
	}

	/**
	 * @param array<string, mixed> $meta
	 * @return object
	 */
	private function make_product( array $meta ): object {
		return new class( $meta ) {
			/**
			 * @param array<string, mixed> $meta
			 */
			public function __construct( private array $meta ) {}

			public function get_id(): int {
				return 10;
			}

			public function is_type( string $type ): bool {
				return ProductMeta::TYPE === $type;
			}

			public function get_meta( string $key, bool $single = true ): mixed {
				return $this->meta[ $key ] ?? '';
			}

			public function get_gallery_image_ids(): array {
				return [];
			}
		};
	}
}
