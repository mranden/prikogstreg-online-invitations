<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\WooCommerce;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductFrontend\BuilderFrontendBridge;
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
		$this->assertStringContainsString( 'background-image: url(https://example.test/envelope.jpg)', $output );
		$this->assertStringContainsString( 'data-pks-oi-envelope-thumbnail-host', $output );
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

		$this->assertStringContainsString( 'data-pks-oi-envelope-artwork="fallback"', $output );
		$this->assertStringNotContainsString( 'pks-oi-product-envelope-preview__invitation-image', $output );
	}

	public function test_renders_page_zero_thumbnail_from_bpp_product_template(): void {
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
					(object) [
						'low_res_html' => '<div>page</div>',
						'thumbnail'    => 'https://example.test/page-thumbnail-0.jpg',
					],
				],
			]
		);

		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				if ( 'bpp/is_product_customizable' === $hook ) {
					return true;
				}

				return $value;
			}
		);

		$product = $this->make_product(
			[
				ProductMeta::ENVELOPE_PRESET   => 'classic',
				ProductMeta::BACKGROUND_PRESET => 'neutral',
			]
		);

		$frontend = new EnvelopeFrontend( new BuilderFrontendBridge( new BuilderService() ) );

		ob_start();
		$frontend->render( $product );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'pks-oi-product-envelope-preview__invitation-image', $output );
		$this->assertStringContainsString( 'https://example.test/page-thumbnail-0.jpg', $output );
		$this->assertStringContainsString( 'data-pks-oi-page-thumbnails', $output );
		$this->assertStringContainsString( 'data-pks-oi-active-page="0"', $output );
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

	public function test_renders_sample_preview_link_when_enabled(): void {
		$product = $this->make_product(
			[
				ProductMeta::ENVELOPE_PRESET       => 'classic',
				ProductMeta::BACKGROUND_PRESET     => 'neutral',
				ProductMeta::DUMMY_PREVIEW_ENABLED => 'yes',
			]
		);

		Functions\when( 'home_url' )->alias(
			static fn( string $path ): string => 'https://example.test' . $path
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

		$this->assertStringContainsString( 'pks-oi-product-envelope-preview__sample-link', $output );
		$this->assertStringContainsString( 'https://example.test/invitation-sample/10/', $output );
		$this->assertStringContainsString( 'target="_blank"', $output );
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

			public function is_visible(): bool {
				return true;
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
