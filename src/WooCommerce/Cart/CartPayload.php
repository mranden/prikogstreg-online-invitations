<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\Cart;

/**
 * Namespaced cart and order-item marker keys for invitation lines.
 */
final class CartPayload {

	public const MARKER_KEY     = 'pks_oi_invitation';
	public const VERSION_KEY    = 'pks_oi_payload_version';
	public const CHECKSUM_KEY     = 'pks_oi_payload_checksum';

	public const CURRENT_VERSION = '1';

	public const ORDER_META_VERSION  = '_pks_oi_payload_version';
	public const ORDER_META_TYPE     = '_pks_oi_product_type';
	public const ORDER_META_CHECKSUM = '_pks_oi_payload_checksum';

	/**
	 * @param array<string, mixed> $cart_item
	 */
	public static function is_invitation_line( array $cart_item ): bool {
		return ! empty( $cart_item[ self::MARKER_KEY ] );
	}

	/**
	 * @param array<string, mixed> $cart_item
	 * @return array<string, mixed>
	 */
	public static function annotate( array $cart_item ): array {
		$cart_item[ self::MARKER_KEY ]  = true;
		$cart_item[ self::VERSION_KEY ] = self::CURRENT_VERSION;

		return $cart_item;
	}
}
