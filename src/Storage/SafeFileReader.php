<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Storage;

use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageChecksumException;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageException;

/**
 * Safe file reads with optional checksum verification.
 */
final class SafeFileReader {

	public function read( string $absolute_path ): string {
		if ( ! is_readable( $absolute_path ) || ! is_file( $absolute_path ) ) {
			throw new StorageException( 'File is not readable.' );
		}

		$content = file_get_contents( $absolute_path );
		if ( false === $content ) {
			throw new StorageException( 'File could not be read.' );
		}

		return $content;
	}

	public function read_verified( string $absolute_path, string $expected_sha256 ): string {
		$content  = $this->read( $absolute_path );
		$checksum = hash( 'sha256', $content );

		if ( ! hash_equals( $expected_sha256, $checksum ) ) {
			throw new StorageChecksumException( 'Checksum mismatch for ' . basename( $absolute_path ) . '.' );
		}

		return $content;
	}

	/**
	 * @return resource
	 */
	public function open_read_stream( string $absolute_path ) {
		$stream = fopen( $absolute_path, 'rb' );
		if ( false === $stream ) {
			throw new StorageException( 'File stream could not be opened.' );
		}

		return $stream;
	}
}
