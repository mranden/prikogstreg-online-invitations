<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\WooCommerce;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Public\Endpoints;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductDummyPreviewController;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductDummyPreviewService;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class ProductDummyPreviewServiceTest extends TestCase {

	public function test_preview_url_uses_product_sample_route(): void {
		Functions\when( 'home_url' )->alias(
			static fn( string $path ): string => 'https://example.test' . $path
		);

		$url = ProductDummyPreviewController::preview_url( 42 );

		$this->assertSame( 'https://example.test/invitation-sample/42/', $url );
	}

	public function test_build_view_model_returns_null_for_disabled_preview(): void {
		$product = $this->make_product(
			[
				ProductMeta::DUMMY_PREVIEW_ENABLED => 'no',
				ProductMeta::ENVELOPE_PRESET       => 'classic',
				ProductMeta::BACKGROUND_PRESET     => 'neutral',
			]
		);

		Functions\when( 'wc_get_product' )->justReturn( $product );

		$service = new ProductDummyPreviewService( new BuilderService() );

		$this->assertNull( $service->build_view_model( 10 ) );
	}

	public function test_build_view_model_includes_configured_sections(): void {
		$product = $this->make_product(
			[
				ProductMeta::DUMMY_PREVIEW_ENABLED      => 'yes',
				ProductMeta::ENVELOPE_PRESET            => 'modern',
				ProductMeta::BACKGROUND_PRESET          => 'floral',
				ProductMeta::DUMMY_GUEST_COUNT          => 3,
				ProductMeta::DUMMY_GIFT_COUNT           => 2,
				ProductMeta::DUMMY_EVENT_DAYS_AHEAD     => 45,
				ProductMeta::DUMMY_EVENT_TITLE          => 'Sample fest',
				ProductMeta::WISHLIST_DEFAULT           => 'yes',
				ProductMeta::GUEST_PHOTOS_DEFAULT     => 'yes',
			]
		);

		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				return $value;
			}
		);
		Functions\when( 'wp_date' )->alias(
			static fn( string $format, int $timestamp ): string => gmdate( $format, $timestamp )
		);
		Functions\when( 'home_url' )->alias(
			static fn( string $path = '' ): string => 'https://example.test' . $path
		);

		$service = new ProductDummyPreviewService( new BuilderService() );
		$view    = $service->build_view_model( 10 );

		$this->assertNotNull( $view );
		$this->assertSame( 'modern', $view->envelope_preset );
		$this->assertSame( 'floral', $view->background_preset );
		$this->assertSame( 'Sample fest', $view->event_title );
		$this->assertSame( 'Anna Jensen', $view->addressee_label );
		$this->assertFalse( $view->track_opens );
		$this->assertSame( '', $view->invitation_token );
		$this->assertSame( '', $view->rsvp_form['rest_url'] ?? 'missing' );
		$this->assertGreaterThanOrEqual( 3, count( $view->wishlist['items'] ?? [] ) );
		$this->assertTrue( $view->wishlist['is_sample'] ?? false );
		$this->assertNotSame( '', $view->photos['qr_svg'] ?? '' );
		$this->assertTrue( $view->photos['is_sample'] ?? false );
		$this->assertTrue( $view->event_details['has_content'] ?? false );

		$section_keys = array_column( $view->sections, 'key' );
		$section_labels = array_column( $view->sections, 'label', 'key' );
		$this->assertContains( 'event', $section_keys );
		$this->assertContains( 'rsvp', $section_keys );
		$this->assertContains( 'wishlist', $section_keys );
		$this->assertContains( 'photos', $section_keys );
		$this->assertSame( 'Example wishlist', $section_labels['wishlist'] ?? '' );
		$this->assertSame( 'Example guest photos', $section_labels['photos'] ?? '' );
	}

	public function test_build_view_model_always_includes_sample_wishlist_and_photos(): void {
		$product = $this->make_product(
			[
				ProductMeta::DUMMY_PREVIEW_ENABLED  => 'yes',
				ProductMeta::ENVELOPE_PRESET        => 'classic',
				ProductMeta::BACKGROUND_PRESET      => 'neutral',
				ProductMeta::WISHLIST_DEFAULT       => 'no',
				ProductMeta::GUEST_PHOTOS_DEFAULT => 'no',
			]
		);

		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				return $value;
			}
		);
		Functions\when( 'home_url' )->alias(
			static fn( string $path = '' ): string => 'https://example.test' . $path
		);
		Functions\when( 'wp_date' )->alias(
			static fn( string $format, int $timestamp ): string => gmdate( $format, $timestamp )
		);

		$service = new ProductDummyPreviewService( new BuilderService() );
		$view    = $service->build_view_model( 10 );

		$this->assertNotNull( $view );
		$this->assertNotEmpty( $view->wishlist['items'] ?? [] );
		$this->assertNotSame( '', $view->photos['qr_svg'] ?? '' );
	}

	public function test_endpoints_declares_product_sample_query_var(): void {
		$endpoints = new Endpoints();
		$vars      = $endpoints->register_query_var( [] );

		$this->assertContains( Endpoints::PRODUCT_SAMPLE_QUERY_VAR, $vars );
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

			public function get_name(): string {
				return 'Online invitation';
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
