<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\WooCommerce;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\WooCommerce\Cart\CartPayload;
use PrikOgStreg\OnlineInvitations\WooCommerce\Cart\CartPayloadValidator;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class CartPayloadValidatorTest extends TestCase {

	private CartPayloadValidator $validator;

	protected function setUp(): void {
		parent::setUp();
		$this->validator = new CartPayloadValidator( new BuilderService() );

		\Brain\Monkey\Functions\when( 'wc_get_product' )->justReturn(
			new class() {
				public function is_type( string $type ): bool {
					return false;
				}

				public function get_meta( string $key, bool $single = true ): string {
					return '';
				}
			}
		);
	}

	public function test_structural_validation_requires_field_page_size_and_format(): void {
		$errors = $this->validator->validate_cart_item(
			[
				CartPayload::MARKER_KEY => true,
				'product_id'            => 10,
				'field'                 => [],
				'page'                  => [],
			]
		);

		$this->assertContains( 'missing_field_payload', $errors );
		$this->assertContains( 'missing_page_payload', $errors );
		$this->assertContains( 'missing_size', $errors );
		$this->assertContains( 'missing_format', $errors );
	}

	public function test_valid_cart_item_passes_structural_validation(): void {
		$errors = $this->validator->validate_cart_item(
			[
				CartPayload::MARKER_KEY => true,
				'product_id'            => 10,
				'field'                 => [ 'uuid-1' => [ 'text' => 'Hello' ] ],
				'page'                  => [ '<div>Page</div>' ],
				'pa_bpp_size'           => 'a5',
				'pa_bpp_format'         => 'flat',
			]
		);

		$this->assertSame( [], $errors );
	}

	public function test_validate_posted_payload_rejects_tampered_size(): void {
		$_POST['field']                    = [ 'uuid-1' => [ 'text' => 'Hello' ] ];
		$_POST['page']                     = [ '<div>Page</div>' ];
		$_POST['attribute_pa_bpp_size']    = 'giant';
		$_POST['attribute_pa_bpp_format']  = 'flat';

		$errors = $this->validator->validate_posted_payload( 10 );

		$this->assertContains( 'bpp_size_not_permitted', $errors );
	}

	public function test_validate_posted_payload_accepts_complete_builder_keys(): void {
		$_POST['field']                    = [ 'uuid-1' => [ 'text' => 'Hello' ] ];
		$_POST['page']                     = [ '<div>Page</div>' ];
		$_POST['attribute_pa_bpp_size']    = 'a5';
		$_POST['attribute_pa_bpp_format']  = 'flat';

		$errors = $this->validator->validate_posted_payload( 10 );

		$this->assertSame( [], $errors );
	}

	public function test_build_state_from_request_normalizes_attributes(): void {
		$_POST['field']                   = [ 'uuid-1' => [ 'text' => 'Hello' ] ];
		$_POST['page']                    = [ '<div>Page</div>' ];
		$_POST['attribute_pa_bpp_size']   = '';
		$_POST['attribute_pa_bpp_format'] = '';

		$state = $this->validator->build_state_from_request( 10 );

		$this->assertSame( 'a5', $state['size'] );
		$this->assertSame( 'flat', $state['format'] );
		$this->assertArrayHasKey( 'field', $state );
		$this->assertArrayHasKey( 'page', $state );
	}

	public function test_checksum_uses_manifest_not_full_payload(): void {
		$checksum_a = $this->validator->compute_checksum(
			[
				'field'      => [ 'a' => [ 'data' => str_repeat( 'x', 5000 ) ] ],
				'page'       => [ str_repeat( 'y', 100 ) ],
				'size'       => 'a5',
				'format'     => 'flat',
				'product_id' => 10,
			]
		);

		$checksum_b = $this->validator->compute_checksum(
			[
				'field'      => [ 'a' => [ 'data' => str_repeat( 'z', 9000 ) ] ],
				'page'       => [ str_repeat( 'q', 100 ) ],
				'size'       => 'a5',
				'format'     => 'flat',
				'product_id' => 10,
			]
		);

		$this->assertSame( $checksum_a, $checksum_b );
	}

	public function test_builder_optional_product_skips_structural_validation(): void {
		\Brain\Monkey\Functions\when( 'wc_get_product' )->justReturn(
			new class() {
				public function is_type( string $type ): bool {
					return ProductMeta::TYPE === $type;
				}

				public function get_meta( string $key, bool $single = true ): string {
					return ProductMeta::BUILDER_OPTIONAL === $key ? 'yes' : '';
				}
			}
		);

		$errors = $this->validator->validate_cart_item(
			[
				CartPayload::MARKER_KEY => true,
				'product_id'            => 10,
				'field'                 => [],
				'page'                  => [],
			]
		);

		$this->assertSame( [], $errors );
	}
}
