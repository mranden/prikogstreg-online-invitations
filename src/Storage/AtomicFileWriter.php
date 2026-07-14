<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Storage;

use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageException;

/**
 * Atomic temp-write with checksum, file locking, and rename.
 */
final class AtomicFileWriter {

	/** @var null|callable(string): void */
	private $after_temp_write_hook = null;

	/**
	 * Test hook invoked after temp file is written but before rename.
	 *
	 * @param null|callable(string): void $hook
	 */
	public function set_after_temp_write_hook( ?callable $hook ): void {
		$this->after_temp_write_hook = $hook;
	}

	public function write( string $target_path, string $content ): string {
		$directory = dirname( $target_path );
		StorageDirectory::ensure( $directory );

		$temp_path = $directory . '/.' . basename( $target_path ) . '.' . getmypid() . '.tmp';

		$handle = fopen( $temp_path, 'cb' );
		if ( false === $handle ) {
			throw new StorageException( 'Could not open temporary file.' );
		}

		try {
			if ( ! flock( $handle, LOCK_EX ) ) {
				throw new StorageException( 'Could not acquire file lock.' );
			}

			if ( false === ftruncate( $handle, 0 ) ) {
				throw new StorageException( 'Could not truncate temporary file.' );
			}

			$written = fwrite( $handle, $content );
			if ( false === $written || $written !== strlen( $content ) ) {
				throw new StorageException( 'Incomplete temporary write.' );
			}

			fflush( $handle );

			$checksum = hash( 'sha256', $content );

			if ( null !== $this->after_temp_write_hook ) {
				( $this->after_temp_write_hook )( $temp_path );
			}

			flock( $handle, LOCK_UN );
			fclose( $handle );
			$handle = null;

			if ( ! rename( $temp_path, $target_path ) ) {
				throw new StorageException( 'Atomic rename failed.' );
			}

			return $checksum;
		} finally {
			if ( is_resource( $handle ) ) {
				flock( $handle, LOCK_UN );
				fclose( $handle );
			}

			if ( is_file( $temp_path ) ) {
				@unlink( $temp_path );
			}
		}
	}
}
