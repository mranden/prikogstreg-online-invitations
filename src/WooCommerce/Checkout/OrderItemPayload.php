<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\Checkout;

use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectImportGuard;
use PrikOgStreg\OnlineInvitations\WooCommerce\Cart\CartPayload;
use PrikOgStreg\OnlineInvitations\WooCommerce\Cart\CartPayloadValidator;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;

/**
 * Copies invitation payload references to order items via WooCommerce CRUD.
 */
final class OrderItemPayload {

	public function __construct(
		private CartPayloadValidator $validator
	) {}

	public function register(): void {
		add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'persist_invitation_references' ], 20, 4 );
		add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'validate_line_before_persist' ], 5, 4 );
		add_action( 'woocommerce_new_order_item', [ $this, 'refresh_checksum_after_bpp_persist' ], 25, 3 );
	}

	/**
	 * @param \WC_Order_Item_Product $item
	 * @param array<string, mixed>   $values
	 */
	public function validate_line_before_persist( $item, string $cart_item_key, array $values, $order ): void {
		if ( ! CartPayload::is_invitation_line( $values ) ) {
			return;
		}

		$errors = $this->validator->validate_cart_item( $values );
		if ( [] === $errors ) {
			return;
		}

		throw new \Exception(
			esc_html__( 'Invitation builder data is missing or invalid. Please return to the cart and customise the product again.', 'prikogstreg-online-invitations' )
		);
	}

	/**
	 * @param \WC_Order_Item_Product $item
	 * @param array<string, mixed>   $values
	 */
	public function persist_invitation_references( $item, string $cart_item_key, array $values, $order ): void {
		if ( ! CartPayload::is_invitation_line( $values ) ) {
			return;
		}

		$item->update_meta_data( CartPayload::ORDER_META_TYPE, ProductMeta::TYPE );
		$item->update_meta_data( CartPayload::ORDER_META_VERSION, (string) ( $values[ CartPayload::VERSION_KEY ] ?? CartPayload::CURRENT_VERSION ) );

		if ( ! empty( $values[ CartPayload::CHECKSUM_KEY ] ) ) {
			$item->update_meta_data( CartPayload::ORDER_META_CHECKSUM, (string) $values[ CartPayload::CHECKSUM_KEY ] );
		}
	}

	/**
	 * Recompute checksum from the persisted BPP payload so import matches order storage.
	 *
	 * @param int                    $item_id
	 * @param \WC_Order_Item_Product $item
	 * @param int                    $order_id
	 */
	public function refresh_checksum_after_bpp_persist( $item_id, $item, $order_id ): void {
		unset( $order_id );

		if ( ! is_object( $item ) || ! method_exists( $item, 'get_meta' ) ) {
			return;
		}

		if ( ProductMeta::TYPE !== (string) $item->get_meta( CartPayload::ORDER_META_TYPE, true ) ) {
			return;
		}

		if ( ! class_exists( 'BPP_Order_Item_Storage', false ) ) {
			return;
		}

		$payload = \BPP_Order_Item_Storage::get_payload( (int) $item_id );
		if ( ! is_array( $payload ) ) {
			return;
		}

		$state = ProjectImportGuard::build_checksum_state(
			[
				'field' => is_array( $payload['field'] ?? null ) ? $payload['field'] : [],
				'page'  => is_array( $payload['page'] ?? null ) ? $payload['page'] : [],
			],
			$item
		);

		if ( null !== ProjectImportGuard::validate_builder_pages( $state ) ) {
			return;
		}

		$checksum = $this->validator->compute_checksum( $state );
		$item->update_meta_data( CartPayload::ORDER_META_CHECKSUM, $checksum );
		$item->save();
	}
}
