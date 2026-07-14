<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\WooCommerce;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\BuilderValidity;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class BuilderValidityTest extends TestCase {

	/** @var array<int, array<string, mixed>> */
	private array $post_meta = [];

	protected function setUp(): void {
		parent::setUp();

		$this->post_meta = [];
		\BPP_Product::reset_test_models();

		Functions\when( 'get_post_meta' )->alias(
			function ( int $post_id, string $key, bool $single ) {
				$value = $this->post_meta[ $post_id ][ $key ] ?? '';

				return $single ? $value : [ $value ];
			}
		);

		Functions\when( 'wc_get_product' )->alias(
			function ( int $product_id ) {
				return isset( $this->post_meta[ $product_id ] )
					? $this->make_invitation_product( $product_id )
					: null;
			}
		);

		Functions\when( 'get_post_type' )->justReturn( 'attachment' );
		Functions\when( 'wp_attachment_is_image' )->justReturn( true );
	}

	protected function tearDown(): void {
		\BPP_Product::reset_test_models();
		parent::tearDown();
	}

	public function test_valid_when_builder_and_presets_are_configured(): void {
		$this->set_active_builder( 10, true );
		$this->set_product_meta(
			10,
			[
				ProductMeta::BUILDER_META_KEY  => (object) [ 'active' => true ],
				ProductMeta::ENVELOPE_PRESET   => 'classic',
				ProductMeta::BACKGROUND_PRESET => 'neutral',
				'_price'                       => '199',
			]
		);

		$this->assertTrue( BuilderValidity::is_valid( 10 ) );
	}

	public function test_invalid_when_builder_template_missing(): void {
		$this->set_active_builder( 11, false );
		$this->set_product_meta(
			11,
			[
				ProductMeta::ENVELOPE_PRESET   => 'classic',
				ProductMeta::BACKGROUND_PRESET => 'neutral',
				'_price'                       => '199',
			]
		);

		$this->assertFalse( BuilderValidity::is_valid( 11 ) );
		$this->assertContains( 'builder_template_missing', BuilderValidity::validation_errors( 11 ) );
	}

	public function test_valid_when_builder_optional_without_template(): void {
		$this->set_active_builder( 13, false );
		$this->set_product_meta(
			13,
			[
				ProductMeta::BUILDER_OPTIONAL  => 'yes',
				ProductMeta::ENVELOPE_PRESET   => 'classic',
				ProductMeta::BACKGROUND_PRESET => 'neutral',
				'_price'                       => '199',
			]
		);

		$this->assertTrue( BuilderValidity::is_valid( 13 ) );
		$this->assertSame( 'testing', BuilderValidity::integration_status( 13 )['status'] );
	}

	public function test_invalid_when_presets_missing(): void {
		$this->set_active_builder( 12, true );
		$this->set_product_meta(
			12,
			[
				ProductMeta::BUILDER_META_KEY => (object) [ 'active' => true ],
				'_price'                      => '199',
			]
		);

		$errors = BuilderValidity::validation_errors( 12 );
		$this->assertContains( 'envelope_preset_missing', $errors );
		$this->assertContains( 'background_preset_missing', $errors );
	}

	public function test_invalid_when_envelope_image_attachment_is_invalid(): void {
		$this->set_active_builder( 14, true );
		$this->set_product_meta(
			14,
			[
				ProductMeta::ENVELOPE_PRESET     => 'classic',
				ProductMeta::BACKGROUND_PRESET   => 'neutral',
				ProductMeta::ENVELOPE_IMAGE_ID   => 88,
				'_price'                         => '199',
			]
		);

		Functions\when( 'wp_attachment_is_image' )->alias(
			static function ( int $attachment_id ): bool {
				return 88 !== $attachment_id;
			}
		);

		$errors = BuilderValidity::validation_errors( 14 );
		$this->assertContains( 'envelope_image_invalid', $errors );
	}

	public function test_invalid_when_price_missing(): void {
		$this->set_active_builder( 15, true );
		$this->set_product_meta(
			15,
			[
				ProductMeta::ENVELOPE_PRESET   => 'classic',
				ProductMeta::BACKGROUND_PRESET => 'neutral',
				'_price'                       => '',
			]
		);

		$errors = BuilderValidity::validation_errors( 15 );
		$this->assertContains( 'price_missing', $errors );
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	private function set_product_meta( int $product_id, array $meta ): void {
		$this->post_meta[ $product_id ] = $meta;
	}

	private function set_active_builder( int $product_id, bool $active ): void {
		\BPP_Product::set_test_model(
			$product_id,
			(object) [
				'active'          => $active,
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
			]
		);
	}

	private function make_invitation_product( int $product_id ): object {
		$meta = $this->post_meta[ $product_id ] ?? [];

		return new class( $product_id, $meta ) {
			/**
			 * @param array<string, mixed> $meta
			 */
			public function __construct(
				private int $id,
				private array $meta
			) {}

			public function get_id(): int {
				return $this->id;
			}

			public function is_type( string $type ): bool {
				return ProductMeta::TYPE === $type;
			}

			public function get_meta( string $key, bool $single = true ): mixed {
				return $this->meta[ $key ] ?? '';
			}

			public function get_price(): string {
				return (string) ( $this->meta['_price'] ?? '' );
			}
		};
	}
}
