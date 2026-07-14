<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\Orders;

/**
 * Detects full refunds of the invitation order line item.
 */
final class OrderRefundDetector {

	/**
	 * @param array<string, mixed> $project
	 */
	public function is_invitation_line_fully_refunded( array $project ): bool {
		$order_id      = (int) ( $project['order_id'] ?? 0 );
		$order_item_id = (int) ( $project['order_item_id'] ?? 0 );
		if ( $order_id <= 0 || $order_item_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
			return false;
		}

		$order = wc_get_order( $order_id );
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) {
			return false;
		}

		$ordered_qty = $this->ordered_quantity( $order, $order_item_id );
		if ( $ordered_qty <= 0 ) {
			return false;
		}

		return $this->refunded_quantity( $order, $order_item_id ) >= $ordered_qty;
	}

	/**
	 * @param object $order
	 */
	private function ordered_quantity( object $order, int $order_item_id ): int {
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_id' ) ) {
				continue;
			}
			if ( (int) $item->get_id() === $order_item_id ) {
				return max( 1, (int) ( method_exists( $item, 'get_quantity' ) ? $item->get_quantity() : 1 ) );
			}
		}

		return 0;
	}

	/**
	 * @param object $order
	 */
	private function refunded_quantity( object $order, int $order_item_id ): int {
		if ( ! method_exists( $order, 'get_refunds' ) ) {
			return 0;
		}

		$refunded = 0;
		foreach ( $order->get_refunds() as $refund ) {
			if ( ! is_object( $refund ) || ! method_exists( $refund, 'get_items' ) ) {
				continue;
			}
			foreach ( $refund->get_items( 'line_item' ) as $item ) {
				if ( ! is_object( $item ) || ! method_exists( $item, 'get_meta' ) ) {
					continue;
				}
				if ( (int) $item->get_meta( '_refunded_item_id' ) !== $order_item_id ) {
					continue;
				}
				$refunded += abs( (int) ( method_exists( $item, 'get_quantity' ) ? $item->get_quantity() : 0 ) );
			}
		}

		return $refunded;
	}

	/**
	 * @param object $order
	 * @param object $refund
	 */
	public function refund_touches_order_item( object $order, object $refund, int $order_item_id ): bool {
		if ( ! method_exists( $refund, 'get_items' ) ) {
			return false;
		}

		foreach ( $refund->get_items( 'line_item' ) as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_meta' ) ) {
				continue;
			}
			if ( (int) $item->get_meta( '_refunded_item_id' ) === $order_item_id ) {
				return true;
			}
		}

		return false;
	}
}
