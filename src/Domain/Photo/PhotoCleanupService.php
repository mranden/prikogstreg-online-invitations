<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Photo;

use PrikOgStreg\OnlineInvitations\Database\Repositories\PhotoRepository;
use PrikOgStreg\OnlineInvitations\Storage\StoragePath;

/**
 * Removes orphaned pending photo files without database rows.
 */
final class PhotoCleanupService {

	public function __construct(
		private StoragePath $paths,
		private PhotoRepository $photos
	) {}

	public function cleanup_orphan_pending( string $storage_uuid, int $max_age_seconds = 3600 ): int {
		try {
			$this->paths->assert_storage_uuid( $storage_uuid );
		} catch ( \Throwable ) {
			return 0;
		}

		$dir = $this->paths->project_root( $storage_uuid ) . '/photos/pending';
		if ( ! is_dir( $dir ) ) {
			return 0;
		}

		$known   = array_fill_keys( $this->photos->list_relative_paths_for_project( $storage_uuid ), true );
		$removed = 0;
		$cutoff  = time() - $max_age_seconds;

		foreach ( glob( $dir . '/*' ) ?: [] as $file ) {
			if ( ! is_file( $file ) ) {
				continue;
			}

			$relative = 'photos/pending/' . basename( $file );
			if ( isset( $known[ $relative ] ) ) {
				continue;
			}

			$mtime = filemtime( $file );
			if ( false !== $mtime && $mtime >= $cutoff ) {
				continue;
			}

			if ( @unlink( $file ) ) {
				++$removed;
			}
		}

		return $removed;
	}
}
