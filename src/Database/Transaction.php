<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Database;

/**
 * Thin transaction wrapper for operations that require atomicity.
 */
final class Transaction {

	/**
	 * @param object $wpdb WordPress database object.
	 */
	public function __construct(
		private object $wpdb
	) {}

	public function run( callable $callback ): mixed {
		$this->wpdb->query( 'START TRANSACTION' );

		try {
			$result = $callback();
			$this->wpdb->query( 'COMMIT' );

			return $result;
		} catch ( \Throwable $e ) {
			$this->wpdb->query( 'ROLLBACK' );
			throw $e;
		}
	}
}
