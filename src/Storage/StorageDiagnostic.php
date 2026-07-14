<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Storage;

use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageChecksumException;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageException;

/**
 * Admin support diagnostics for project storage health.
 */
final class StorageDiagnostic {

	public function __construct(
		private StoragePath $paths,
		private ProjectStorage $storage,
		private StorageCleanup $cleanup
	) {}

	/**
	 * @param array<string, mixed> $project
	 * @return array<string, mixed>
	 */
	public function diagnose_project( array $project ): array {
		$storage_uuid = (string) ( $project['storage_uuid'] ?? '' );
		$issues       = [];

		try {
			$this->paths->assert_storage_uuid( $storage_uuid );
		} catch ( \Throwable $e ) {
			return [
				'healthy' => false,
				'issues'  => [ 'invalid_storage_uuid' ],
			];
		}

		$root            = $this->paths->root();
		$project_root    = $this->paths->project_root( $storage_uuid );
		$root_writable   = is_writable( $root );
		$project_exists  = is_dir( $project_root );
		$manifest_exists = is_readable( $project_root . '/manifest.json' );
		$checksums_valid = false;
		$published_exists = is_readable( $project_root . '/published/manifest.json' );
		$temp_pending    = 0;

		if ( ! $root_writable ) {
			$issues[] = 'storage_root_not_writable';
		}

		if ( ! $project_exists ) {
			$issues[] = 'project_directory_missing';
		}

		if ( $manifest_exists ) {
			try {
				$this->storage->verify_manifest_integrity( $storage_uuid );
				$checksums_valid = true;
			} catch ( StorageChecksumException ) {
				$issues[] = 'checksum_mismatch';
			} catch ( StorageException $e ) {
				$issues[] = $e->code_key;
			}
		} else {
			$issues[] = 'state_manifest_missing';
		}

		if ( $published_exists ) {
			try {
				$this->storage->read_published_manifest( $storage_uuid, true );
			} catch ( StorageChecksumException ) {
				$issues[] = 'published_checksum_mismatch';
			} catch ( StorageException $e ) {
				$issues[] = $e->code_key;
			}
		}

		$temp_pending = $this->cleanup->cleanup_abandoned_temp_files( $storage_uuid, PHP_INT_MAX );

		return [
			'healthy'                    => [] === $issues,
			'uses_fallback_root'         => $this->paths->uses_fallback_root(),
			'storage_root'               => $root,
			'root_writable'              => $root_writable,
			'project_directory_exists'   => $project_exists,
			'state_manifest_exists'      => $manifest_exists,
			'checksums_valid'            => $checksums_valid,
			'published_manifest_exists'  => $published_exists,
			'state_manifest_path'        => (string) ( $project['state_manifest_path'] ?? '' ),
			'published_manifest_path'    => (string) ( $project['published_manifest_path'] ?? '' ),
			'state_version'              => (int) ( $project['state_version'] ?? 0 ),
			'published_version'          => isset( $project['published_version'] ) ? (int) $project['published_version'] : null,
			'abandoned_temp_files'       => $temp_pending,
			'issues'                     => $issues,
		];
	}
}
