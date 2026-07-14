<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Database;

/**
 * Runs pending migrations during normal plugin bootstrap.
 */
final class DatabaseBootstrap {

	public function register(): void {
		add_action( 'init', [ $this, 'maybe_migrate' ], 1 );
	}

	public function maybe_migrate(): void {
		global $wpdb;

		$migrator = new Migrator( $wpdb );
		$migrator->maybe_migrate();
	}
}
