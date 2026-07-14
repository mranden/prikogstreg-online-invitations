<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Storage;

/**
 * Cleanup helpers for temp files and project trees.
 */
final class StorageCleanup {

	public function __construct(
		private StoragePath $paths
	) {}

	public function cleanup_abandoned_temp_files( string $storage_uuid, int $max_age_seconds = StorageLimits::TEMP_MAX_AGE_SECONDS ): int {
		$tmp_dir = $this->paths->project_tmp_dir( $storage_uuid );
		if ( ! is_dir( $tmp_dir ) ) {
			return 0;
		}

		$removed = 0;
		$cutoff  = time() - $max_age_seconds;

		foreach ( glob( $tmp_dir . '/*' ) ?: [] as $file ) {
			if ( ! is_file( $file ) ) {
				continue;
			}

			$mtime = filemtime( $file );
			if ( false !== $mtime && $mtime < $cutoff ) {
				if ( @unlink( $file ) ) {
					++$removed;
				}
			}
		}

		return $removed;
	}

	public function delete_project_tree( string $storage_uuid ): bool {
		$this->paths->assert_storage_uuid( $storage_uuid );

		$project_root = $this->paths->project_root( $storage_uuid );
		if ( ! is_dir( $project_root ) ) {
			return true;
		}

		$this->delete_directory( $project_root );

		return ! is_dir( $project_root );
	}

	private function delete_directory( string $directory ): void {
		$items = scandir( $directory );
		if ( false === $items ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$path = $directory . '/' . $item;
			if ( is_dir( $path ) ) {
				$this->delete_directory( $path );
				continue;
			}

			@unlink( $path );
		}

		@rmdir( $directory );
	}
}
