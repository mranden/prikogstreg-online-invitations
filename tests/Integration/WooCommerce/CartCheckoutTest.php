<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\WooCommerce;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\WooCommerce\Cart\CartPayload;
use PrikOgStreg\OnlineInvitations\WooCommerce\Cart\CartPayloadValidator;
use PrikOgStreg\OnlineInvitations\WooCommerce\Cart\InvitationCart;
use PrikOgStreg\OnlineInvitations\WooCommerce\Checkout\AccountRequirement;
use PrikOgStreg\OnlineInvitations\WooCommerce\Checkout\OrderItemPayload;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class CartCheckoutTest extends TestCase {

	public function test_invitation_cart_annotates_marker_and_checksum(): void {
		$_POST = [
			'field'                  => [ 'uuid-1' => [ 'text' => 'Hello' ] ],
			'page'                   => [ '<div>Page</div>' ],
			'attribute_pa_bpp_size'  => 'a5',
			'attribute_pa_bpp_format' => 'flat',
		];

		$product = $this->make_product( 10, ProductMeta::TYPE );
		Functions\when( 'wc_get_product' )->justReturn( $product );

		$cart    = new InvitationCart( new CartPayloadValidator( new BuilderService() ) );
		$payload = $cart->annotate_invitation_line( [], 10, 0 );

		$this->assertTrue( $payload[ CartPayload::MARKER_KEY ] );
		$this->assertSame( CartPayload::CURRENT_VERSION, $payload[ CartPayload::VERSION_KEY ] );
		$this->assertNotEmpty( $payload[ CartPayload::CHECKSUM_KEY ] );

		unset( $_POST );
	}

	public function test_session_restore_preserves_invitation_markers(): void {
		$cart = new InvitationCart( new CartPayloadValidator( new BuilderService() ) );
		$item = $cart->restore_from_session(
			[ 'product_id' => 10 ],
			[
				CartPayload::MARKER_KEY  => true,
				CartPayload::VERSION_KEY => CartPayload::CURRENT_VERSION,
				CartPayload::CHECKSUM_KEY => 'abc123',
			]
		);

		$this->assertTrue( $item[ CartPayload::MARKER_KEY ] );
		$this->assertSame( 'abc123', $item[ CartPayload::CHECKSUM_KEY ] );
	}

	public function test_invalid_bpp_attributes_reject_add_to_cart(): void {
		$_POST = [
			'field'                   => [ 'uuid-1' => [ 'text' => 'Hello' ] ],
			'page'                    => [ '<div>Page</div>' ],
			'attribute_pa_bpp_size'   => 'invalid-size',
			'attribute_pa_bpp_format' => 'flat',
		];

		$product = $this->make_product( 10, ProductMeta::TYPE );
		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\expect( 'wc_add_notice' )->once();

		$cart = new InvitationCart( new CartPayloadValidator( new BuilderService() ) );
		$this->assertFalse( $cart->validate_builder_payload( true, 10, 1 ) );

		unset( $_POST );
	}

	public function test_unresolved_bpp_defaults_reject_add_to_cart(): void {
		$_POST = [
			'field' => [ 'uuid-1' => [ 'text' => 'Hello' ] ],
			'page'  => [ '<div>Page</div>' ],
		];

		\BPP_Product::set_test_model(
			10,
			(object) [
				'active'          => true,
				'type'            => 'invitation',
				'foldable'        => false,
				'default_size'    => 'a5',
				'available_sizes' => [ 'invitation' => [] ],
			]
		);

		$product = $this->make_product( 10, ProductMeta::TYPE );
		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\expect( 'wc_add_notice' )->once();

		$cart = new InvitationCart( new CartPayloadValidator( new BuilderService() ) );
		$this->assertFalse( $cart->validate_builder_payload( true, 10, 1 ) );

		\BPP_Product::reset_test_models();
		unset( $_POST );
	}

	public function test_invalid_builder_payload_rejects_add_to_cart(): void {
		$_POST = [];

		$product = $this->make_product( 10, ProductMeta::TYPE );
		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\expect( 'wc_add_notice' )->once();

		$cart = new InvitationCart( new CartPayloadValidator( new BuilderService() ) );
		$this->assertFalse( $cart->validate_builder_payload( true, 10, 1 ) );

		unset( $_POST );
	}

	public function test_account_requirement_forces_registration_when_cart_has_invitation(): void {
		$cart = $this->make_cart_stub( true );
		Functions\when( 'WC' )->justReturn( (object) [ 'cart' => $cart ] );

		$requirement = new AccountRequirement();
		$this->assertTrue( $requirement->registration_required( false ) );
		$this->assertSame( 'no', $requirement->disable_guest_checkout_option( 'yes' ) );
	}

	public function test_logged_in_customer_passes_account_requirement(): void {
		$cart = $this->make_cart_stub( true );
		Functions\when( 'WC' )->justReturn( (object) [ 'cart' => $cart ] );
		Functions\when( 'is_user_logged_in' )->justReturn( true );

		$requirement = new AccountRequirement();
		$requirement->validate_account_requirement();

		$this->assertTrue( true );
	}

	public function test_order_item_payload_persists_reference_meta(): void {
		$item = new class() {
			/** @var array<string, mixed> */
			public array $meta = [];

			public function update_meta_data( string $key, $value ): void {
				$this->meta[ $key ] = $value;
			}
		};

		$payload = new OrderItemPayload( new CartPayloadValidator( new BuilderService() ) );
		$payload->persist_invitation_references(
			$item,
			'line-1',
			[
				CartPayload::MARKER_KEY  => true,
				CartPayload::VERSION_KEY => CartPayload::CURRENT_VERSION,
				CartPayload::CHECKSUM_KEY => 'checksum-value',
				'field'                  => [ 'uuid' => [ 'text' => 'Hi' ] ],
				'page'                   => [ '<div>Page</div>' ],
				'pa_bpp_size'            => 'a5',
				'pa_bpp_format'          => 'flat',
				'product_id'             => 10,
			],
			null
		);

		$this->assertSame( ProductMeta::TYPE, $item->meta[ CartPayload::ORDER_META_TYPE ] );
		$this->assertSame( CartPayload::CURRENT_VERSION, $item->meta[ CartPayload::ORDER_META_VERSION ] );
		$this->assertSame( 'checksum-value', $item->meta[ CartPayload::ORDER_META_CHECKSUM ] );
	}

	public function test_order_item_payload_rejects_invalid_line_at_checkout(): void {
		$this->expectException( \Exception::class );

		$item = new class() {
			public function update_meta_data( string $key, $value ): void {}
		};

		$payload = new OrderItemPayload( new CartPayloadValidator( new BuilderService() ) );
		$payload->validate_line_before_persist(
			$item,
			'line-1',
			[
				CartPayload::MARKER_KEY => true,
				'product_id'          => 10,
				'field'                 => [],
				'page'                  => [],
			],
			null
		);
	}

	public function test_guest_without_create_account_fails_validation(): void {
		$cart = $this->make_cart_stub( true );
		Functions\when( 'WC' )->justReturn( (object) [ 'cart' => $cart ] );
		Functions\when( 'is_user_logged_in' )->justReturn( false );
		Functions\expect( 'wc_add_notice' )->once();

		$_POST = [];

		$requirement = new AccountRequirement();
		$requirement->validate_account_requirement();

		$this->assertTrue( true );

		unset( $_POST );
	}

	public function test_mixed_cart_only_annotates_invitation_line(): void {
		$_POST = [
			'field'                     => [ 'uuid-1' => [ 'text' => 'Hello' ] ],
			'page'                      => [ '<div>Page</div>' ],
			'attribute_pa_bpp_size'     => 'a5',
			'attribute_pa_bpp_format'   => 'flat',
		];

		$invitation = $this->make_product( 10, ProductMeta::TYPE );
		$simple     = $this->make_product( 20, 'simple' );

		Functions\when( 'wc_get_product' )->alias(
			static fn( int $id ) => 10 === $id ? $invitation : $simple
		);

		$cart = new InvitationCart( new CartPayloadValidator( new BuilderService() ) );

		$this->assertArrayNotHasKey( CartPayload::MARKER_KEY, $cart->annotate_invitation_line( [], 20, 0 ) );
		$this->assertArrayHasKey( CartPayload::MARKER_KEY, $cart->annotate_invitation_line( [], 10, 0 ) );

		unset( $_POST );
	}

	private function make_product( int $id, string $type ): object {
		return new class( $id, $type ) {
			public function __construct(
				private int $id,
				private string $type
			) {}

			public function get_id(): int {
				return $this->id;
			}

			public function is_type( string $type ): bool {
				return $this->type === $type;
			}

			public function get_meta( string $key, bool $single = true ): string {
				return '';
			}
		};
	}

	private function make_cart_stub( bool $has_invitation ): object {
		return new class( $has_invitation ) {
			public function __construct( private bool $has_invitation ) {}

			/**
			 * @return list<array<string, mixed>>
			 */
			public function get_cart(): array {
				if ( ! $this->has_invitation ) {
					return [];
				}

				return [
					[
						CartPayload::MARKER_KEY => true,
						'product_id'          => 10,
					],
				];
			}
		};
	}
}
