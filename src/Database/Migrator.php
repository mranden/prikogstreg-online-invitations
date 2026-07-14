<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Database;

use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoShareTokenService;

/**
 * Runs versioned schema installation via WordPress dbDelta().
 */
final class Migrator {

	public const OPTION_DB_VERSION     = 'pks_oi_db_version';
	public const OPTION_MIGRATION_ERROR = 'pks_oi_migration_error';

	/**
	 * @param object $wpdb WordPress database object.
	 */
	public function __construct(
		private object $wpdb
	) {}

	public function get_installed_version(): int {
		return (int) get_option( self::OPTION_DB_VERSION, 0 );
	}

	public function needs_migration(): bool {
		return $this->get_installed_version() < Schema::CURRENT_VERSION;
	}

	public function install(): bool {
		if ( ! MigrationLock::acquire() ) {
			$this->store_error( 'migration_lock_active' );

			return false;
		}

		try {
			$this->load_db_delta();

			$collate = $this->wpdb->get_charset_collate();
			$queries = Schema::get_definitions( $this->wpdb->prefix, $collate );

			foreach ( $queries as $sql ) {
				dbDelta( $sql );
			}

			update_option( self::OPTION_DB_VERSION, Schema::CURRENT_VERSION, false );
			delete_option( self::OPTION_MIGRATION_ERROR );

			if ( Schema::CURRENT_VERSION >= 3 ) {
				$this->run_photo_share_backfill();
			}

			if ( Schema::CURRENT_VERSION >= 4 ) {
				$this->run_photo_feature_defaults();
			}

			return true;
		} catch ( \Throwable $e ) {
			$this->store_error( 'migration_failed' );

			return false;
		} finally {
			MigrationLock::release();
		}
	}

	public function maybe_migrate(): bool {
		if ( ! $this->needs_migration() ) {
			return true;
		}

		return $this->install();
	}

	public function get_last_error(): string {
		return (string) get_option( self::OPTION_MIGRATION_ERROR, '' );
	}

	private function store_error( string $code ): void {
		update_option( self::OPTION_MIGRATION_ERROR, $code, false );
	}

	private function load_db_delta(): void {
		if ( ! function_exists( 'dbDelta' ) ) {
			$upgrade = ABSPATH . 'wp-admin/includes/upgrade.php';
			if ( is_readable( $upgrade ) ) {
				require_once $upgrade;
			}
		}

		if ( ! function_exists( 'dbDelta' ) ) {
			throw new \RuntimeException( 'dbDelta is unavailable.' );
		}
	}

	private function run_photo_share_backfill(): void {
		$registry = new RepositoryRegistry( $this->wpdb );
		( new PhotoShareBackfill(
			$registry->projects(),
			new PhotoShareTokenService( $registry->projects() )
		) )->run();
	}

	private function run_photo_feature_defaults(): void {
		$table = $this->wpdb->prefix . 'pks_oi_projects';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
		$this->wpdb->query(
			"UPDATE {$table} SET photo_auto_approve_enabled = 1, photo_gallery_public_enabled = 1 WHERE guest_photos_enabled = 1"
		);
	}
}
