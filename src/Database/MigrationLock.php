<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Database;

/**
 * Short-lived migration lock to prevent concurrent schema updates.
 */
final class MigrationLock {

	private const OPTION_KEY = 'pks_oi_migration_lock';
	private const TTL_SECONDS = 300;

	public static function acquire(): bool {
		$now = time();
		$lock = get_option( self::OPTION_KEY, null );

		if ( is_array( $lock ) && isset( $lock['expires_at'] ) && (int) $lock['expires_at'] > $now ) {
			return false;
		}

		update_option(
			self::OPTION_KEY,
			[
				'acquired_at' => $now,
				'expires_at'  => $now + self::TTL_SECONDS,
			],
			false
		);

		return true;
	}

	public static function release(): void {
		delete_option( self::OPTION_KEY );
	}

	public static function is_locked(): bool {
		$lock = get_option( self::OPTION_KEY, null );

		return is_array( $lock )
			&& isset( $lock['expires_at'] )
			&& (int) $lock['expires_at'] > time();
	}
}
