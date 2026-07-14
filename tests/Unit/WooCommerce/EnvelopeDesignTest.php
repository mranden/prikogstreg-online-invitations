<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\WooCommerce;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\AttachmentValidator;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\EnvelopeDesign;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class EnvelopeDesignTest extends TestCase {

	protected function tearDown(): void {
		\BPP_Product::reset_test_models();
		parent::tearDown();
	}

	public function test_explicit_envelope_image_takes_priority_over_gallery(): void {
		$product = $this->make_product(
			[
				ProductMeta::ENVELOPE_PRESET      => 'classic',
				ProductMeta::BACKGROUND_PRESET    => 'neutral',
				ProductMeta::ENVELOPE_IMAGE_ID    => 42,
			],
			[ 99, 100 ]
		);

		Functions\when( 'get_post_type' )->justReturn( 'attachment' );
		Functions\when( 'wp_attachment_is_image' )->justReturn( true );
		Functions\when( 'wp_get_attachment_image_url' )->alias(
			static function ( int $attachment_id ): string {
				return 'https://example.test/image-' . $attachment_id . '.jpg';
			}
		);

		$design = EnvelopeDesign::resolve_for_product( $product );

		$this->assertSame( 42, $design['image_id'] );
		$this->assertSame( EnvelopeDesign::SOURCE_EXPLICIT_IMAGE, $design['image_source'] );
		$this->assertSame( 'https://example.test/image-42.jpg', $design['image_url'] );
	}

	public function test_preset_only_when_no_explicit_image_or_gallery(): void {
		$product = $this->make_product(
			[
				ProductMeta::ENVELOPE_PRESET   => 'modern',
				ProductMeta::BACKGROUND_PRESET => 'floral',
			],
			[]
		);

		$design = EnvelopeDesign::resolve_for_product( $product );

		$this->assertSame( 'modern', $design['preset'] );
		$this->assertSame( 0, $design['image_id'] );
		$this->assertSame( EnvelopeDesign::SOURCE_PRESET, $design['image_source'] );
	}

	public function test_gallery_fallback_uses_first_gallery_image_only(): void {
		$product = $this->make_product(
			[
				ProductMeta::ENVELOPE_PRESET   => 'classic',
				ProductMeta::BACKGROUND_PRESET => 'neutral',
			],
			[ 201, 202 ]
		);

		Functions\when( 'get_post_type' )->justReturn( 'attachment' );
		Functions\when( 'wp_attachment_is_image' )->justReturn( true );
		Functions\when( 'wp_get_attachment_image_url' )->alias(
			static function ( int $attachment_id ): string {
				return 'https://example.test/gallery-' . $attachment_id . '.jpg';
			}
		);

		$design = EnvelopeDesign::resolve_for_product( $product );

		$this->assertSame( 201, $design['image_id'] );
		$this->assertSame( EnvelopeDesign::SOURCE_GALLERY, $design['image_source'] );
	}

	public function test_invalid_explicit_image_does_not_fall_back_to_gallery(): void {
		$product = $this->make_product(
			[
				ProductMeta::ENVELOPE_IMAGE_ID => 77,
			],
			[ 301 ]
		);

		Functions\when( 'get_post_type' )->alias(
			static function ( int $post_id ): string {
				return 77 === $post_id ? 'attachment' : 'attachment';
			}
		);
		Functions\when( 'wp_attachment_is_image' )->alias(
			static function ( int $post_id ): bool {
				return 301 === $post_id;
			}
		);
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( 'https://example.test/gallery-301.jpg' );

		$design = EnvelopeDesign::resolve_for_product( $product );

		$this->assertSame( 0, $design['image_id'] );
		$this->assertSame( EnvelopeDesign::SOURCE_PRESET, $design['image_source'] );
	}

	public function test_attachment_validator_rejects_non_image_attachment(): void {
		Functions\when( 'get_post_type' )->justReturn( 'attachment' );
		Functions\when( 'wp_attachment_is_image' )->justReturn( false );
		Functions\when( 'get_post_mime_type' )->justReturn( 'application/pdf' );

		$this->assertFalse( AttachmentValidator::is_valid_image_attachment( 10 ) );
	}

	public function test_project_snapshot_uses_resolved_image_not_later_product_changes(): void {
		$product = $this->make_product(
			[
				ProductMeta::ENVELOPE_PRESET   => 'classic',
				ProductMeta::BACKGROUND_PRESET => 'neutral',
			],
			[ 501 ]
		);

		Functions\when( 'get_post_type' )->justReturn( 'attachment' );
		Functions\when( 'wp_attachment_is_image' )->justReturn( true );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( 'https://example.test/gallery-501.jpg' );

		$factory = new \PrikOgStreg\OnlineInvitations\Domain\Project\ProjectFactory();
		$row     = $factory->build_initial_row(
			[
				'project_id'         => 1,
				'storage_uuid'       => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
				'user_id'            => 2,
				'order_id'           => 3,
				'order_item_id'      => 4,
				'product_id'         => 10,
				'generic_token_hash' => str_repeat( 'a', 64 ),
				'product'            => $product,
			]
		);

		$this->assertSame( 501, $row['envelope_image_id'] );

		$product->set_gallery_image_ids( [ 999 ] );
		$row_after_change = $factory->build_initial_row(
			[
				'project_id'         => 2,
				'storage_uuid'       => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
				'user_id'            => 2,
				'order_id'           => 3,
				'order_item_id'      => 5,
				'product_id'         => 10,
				'generic_token_hash' => str_repeat( 'b', 64 ),
				'product'            => $product,
			]
		);

		$this->assertSame( 501, $row['envelope_image_id'] );
		$this->assertSame( 999, EnvelopeDesign::resolve_for_product( $product )['image_id'] );
	}

	public function test_simple_product_type_is_unaffected(): void {
		$product = $this->make_product( [], [], 'simple' );

		$this->assertFalse( ProductMeta::is_online_invitation( $product ) );
	}

	/**
	 * @param array<string, mixed> $meta
	 * @param list<int>            $gallery
	 */
	private function make_product( array $meta, array $gallery = [], string $type = ProductMeta::TYPE ): object {
		return new class( $meta, $gallery, $type ) {
			/** @var list<int> */
			private array $gallery;

			/**
			 * @param array<string, mixed> $meta
			 * @param list<int>            $gallery
			 */
			public function __construct(
				private array $meta,
				array $gallery,
				private string $type
			) {
				$this->gallery = $gallery;
			}

			public function get_id(): int {
				return 10;
			}

			public function is_type( string $type ): bool {
				return $this->type === $type;
			}

			public function get_meta( string $key, bool $single = true ): mixed {
				return $this->meta[ $key ] ?? '';
			}

			/**
			 * @return list<int>
			 */
			public function get_gallery_image_ids(): array {
				return $this->gallery;
			}

			/**
			 * @param list<int> $gallery
			 */
			public function set_gallery_image_ids( array $gallery ): void {
				$this->gallery = $gallery;
			}
		};
	}
}
