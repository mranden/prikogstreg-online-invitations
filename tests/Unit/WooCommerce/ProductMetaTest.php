<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\WooCommerce;

use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class ProductMetaTest extends TestCase {

	public function test_product_type_slug_is_online_invitation(): void {
		$this->assertSame( 'online_invitation', ProductMeta::TYPE );
	}

	public function test_preset_allowlists_include_expected_values(): void {
		$this->assertArrayHasKey( 'classic', ProductMeta::envelope_presets() );
		$this->assertArrayHasKey( 'neutral', ProductMeta::background_presets() );
	}

	public function test_save_admin_fields_persists_valid_configuration(): void {
		$product = new class() {
			/** @var array<string, mixed> */
			private array $meta = [];

			public function update_meta_data( string $key, $value ): void {
				$this->meta[ $key ] = $value;
			}

			public function get_meta( string $key ): mixed {
				return $this->meta[ $key ] ?? '';
			}
		};

		if ( ! function_exists( 'wc_string_to_bool' ) ) {
			function wc_string_to_bool( $value ): bool {
				return in_array( strtolower( (string) $value ), [ '1', 'true', 'yes' ], true );
			}
		}

		ProductMeta::save_admin_fields(
			$product,
			[
				ProductMeta::ENVELOPE_PRESET      => 'modern',
				ProductMeta::ENVELOPE_PREVIEW_REF => 'preview-ref-1',
				ProductMeta::BACKGROUND_PRESET    => 'floral',
				ProductMeta::DEFAULT_LOCALE       => 'da_DK',
				ProductMeta::REMINDER_OFFSET_DAYS => '7',
				ProductMeta::GUEST_PHOTOS_DEFAULT => 'on',
				ProductMeta::WISHLIST_DEFAULT     => 'on',
			]
		);

		$this->assertSame( 'modern', $product->get_meta( ProductMeta::ENVELOPE_PRESET ) );
		$this->assertSame( 'preview-ref-1', $product->get_meta( ProductMeta::ENVELOPE_PREVIEW_REF ) );
		$this->assertSame( 'floral', $product->get_meta( ProductMeta::BACKGROUND_PRESET ) );
		$this->assertSame( 7, $product->get_meta( ProductMeta::REMINDER_OFFSET_DAYS ) );
	}
}
