<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\Orders;

/**
 * Short-lived per-order-item lock to prevent duplicate project creation.
 */
final class ProjectCreationLock {

	private const OPTION_PREFIX = 'pks_oi_project_creation_lock_';
	private const TTL_SECONDS = 120;

	public static function acquire( int $order_item_id ): bool {
		$key  = self::option_key( $order_item_id );
		$now  = time();
		$lock = get_option( $key, null );

		if ( is_array( $lock ) && isset( $lock['expires_at'] ) && (int) $lock['expires_at'] > $now ) {
			return false;
		}

		update_option(
			$key,
			[
				'acquired_at' => $now,
				'expires_at'  => $now + self::TTL_SECONDS,
			],
			false
		);

		return true;
	}

	public static function release( int $order_item_id ): void {
		delete_option( self::option_key( $order_item_id ) );
	}

	public static function is_locked( int $order_item_id ): bool {
		$lock = get_option( self::option_key( $order_item_id ), null );

		return is_array( $lock )
			&& isset( $lock['expires_at'] )
			&& (int) $lock['expires_at'] > time();
	}

	private static function option_key( int $order_item_id ): string {
		return self::OPTION_PREFIX . $order_item_id;
	}
}
