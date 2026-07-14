<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\WooCommerce;

use Brain\Monkey\Filters;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\BppAttributeDefaults;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class BppAttributeDefaultsTest extends TestCase {

	protected function tearDown(): void {
		\BPP_Product::reset_test_models();
		parent::tearDown();
	}

	public function test_resolve_from_model_uses_default_size_when_permitted(): void {
		$result = BppAttributeDefaults::resolve_from_model(
			[
				'type'            => 'invitation',
				'foldable'        => false,
				'default_size'    => 'a6',
				'available_sizes' => [
					'invitation' => [
						[
							'attribute_slug' => 'a5',
							'available'      => true,
						],
						[
							'attribute_slug' => 'a6',
							'available'      => true,
						],
					],
				],
			]
		);

		$this->assertSame( 'a6', $result['size'] );
		$this->assertSame( 'flat', $result['format'] );
	}

	public function test_resolve_from_model_falls_back_to_first_available_size(): void {
		$result = BppAttributeDefaults::resolve_from_model(
			[
				'type'            => 'invitation',
				'foldable'        => false,
				'default_size'    => 'missing',
				'available_sizes' => [
					'invitation' => [
						[
							'attribute_slug' => '14-x-14',
							'available'      => true,
						],
					],
				],
			]
		);

		$this->assertSame( '14-x-14', $result['size'] );
	}

	public function test_resolve_from_model_includes_folded_format_for_foldable_invitation(): void {
		$result = BppAttributeDefaults::resolve_from_model(
			[
				'type'            => 'invitation',
				'foldable'        => true,
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

		$this->assertSame( 'flat', $result['format'] );
	}

	public function test_resolve_from_model_rejects_missing_available_sizes(): void {
		$result = BppAttributeDefaults::resolve_from_model(
			[
				'type'            => 'invitation',
				'foldable'        => false,
				'default_size'    => 'a5',
				'available_sizes' => [
					'invitation' => [
						[
							'attribute_slug' => 'a5',
							'available'      => false,
						],
					],
				],
			]
		);

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'bpp_size_unavailable', $result->get_error_code() );
	}

	public function test_filter_can_override_defaults_when_values_remain_permitted(): void {
		Filters\expectApplied( BppAttributeDefaults::FILTER )
			->once()
			->andReturnUsing(
				static function ( array $defaults ): array {
					$defaults['format'] = 'folded';

					return $defaults;
				}
			);

		$result = BppAttributeDefaults::resolve_from_model(
			[
				'type'            => 'invitation',
				'foldable'        => true,
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

		$this->assertSame( 'folded', $result['format'] );
	}

	public function test_filter_rejects_invalid_override(): void {
		Filters\expectApplied( BppAttributeDefaults::FILTER )
			->once()
			->andReturnUsing(
				static function ( array $defaults ): array {
					$defaults['size'] = 'not-a-real-size';

					return $defaults;
				}
			);

		$result = BppAttributeDefaults::resolve_from_model(
			[
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

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'bpp_size_invalid', $result->get_error_code() );
	}

	public function test_normalize_posted_attributes_fills_missing_values_from_defaults(): void {
		$result = BppAttributeDefaults::normalize_posted_attributes( 10, '', '' );

		$this->assertSame( 'a5', $result['size'] );
		$this->assertSame( 'flat', $result['format'] );
	}

	public function test_normalize_posted_attributes_rejects_untrusted_size_slug(): void {
		$result = BppAttributeDefaults::normalize_posted_attributes( 10, 'giant', 'flat' );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'bpp_size_not_permitted', $result->get_error_code() );
	}

	public function test_resolve_returns_error_for_inactive_builder(): void {
		\BPP_Product::set_test_model(
			10,
			(object) [
				'active'          => false,
				'type'            => 'invitation',
				'foldable'        => false,
				'default_size'    => 'a5',
				'available_sizes' => [ 'invitation' => [] ],
			]
		);

		$result = BppAttributeDefaults::resolve( 10 );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'bpp_inactive', $result->get_error_code() );
	}
}
