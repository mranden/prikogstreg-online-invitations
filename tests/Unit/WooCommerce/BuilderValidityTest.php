<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\WooCommerce;

use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\BuilderValidity;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class BuilderValidityTest extends TestCase {

	/** @var array<int, array<string, mixed>> */
	private array $post_meta = [];

	protected function setUp(): void {
		parent::setUp();

		$this->post_meta = [];

		\Brain\Monkey\Functions\when( 'get_post_meta' )->alias(
			function ( int $post_id, string $key, bool $single ) {
				$value = $this->post_meta[ $post_id ][ $key ] ?? '';
				return $single ? $value : [ $value ];
			}
		);
	}

	public function test_valid_when_builder_and_presets_are_configured(): void {
		$this->set_product_meta(
			10,
			[
				ProductMeta::BUILDER_META_KEY    => (object) [ 'active' => true ],
				ProductMeta::ENVELOPE_PRESET     => 'classic',
				ProductMeta::BACKGROUND_PRESET   => 'neutral',
			]
		);

		$this->assertTrue( BuilderValidity::is_valid( 10 ) );
	}

	public function test_invalid_when_builder_template_missing(): void {
		$this->set_product_meta(
			11,
			[
				ProductMeta::ENVELOPE_PRESET   => 'classic',
				ProductMeta::BACKGROUND_PRESET => 'neutral',
			]
		);

		$this->assertFalse( BuilderValidity::is_valid( 11 ) );
		$this->assertContains( 'builder_template_missing', BuilderValidity::validation_errors( 11 ) );
	}

	public function test_invalid_when_presets_missing(): void {
		$this->set_product_meta(
			12,
			[
				ProductMeta::BUILDER_META_KEY => (object) [ 'active' => true ],
			]
		);

		$errors = BuilderValidity::validation_errors( 12 );
		$this->assertContains( 'envelope_preset_missing', $errors );
		$this->assertContains( 'background_preset_missing', $errors );
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	private function set_product_meta( int $product_id, array $meta ): void {
		$this->post_meta[ $product_id ] = $meta;
	}
}
