<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Storage;

use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageChecksumException;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageException;

/**
 * Authorized streaming helper — never exposes direct public URLs.
 */
final class FileStreamResponse {

	public function __construct(
		private StoragePath $paths,
		private SafeFileReader $reader
	) {}

	public function open_relative(
		string $storage_uuid,
		string $relative_path,
		string $mime_type,
		?string $expected_sha256 = null
	): StreamHandle {
		$absolute = $this->paths->absolute_from_relative( $storage_uuid, $relative_path );

		if ( null !== $expected_sha256 ) {
			$this->reader->read_verified( $absolute, $expected_sha256 );
		} elseif ( ! is_readable( $absolute ) ) {
			throw new StorageException( 'Stream target is not readable.' );
		}

		$stream = $this->reader->open_read_stream( $absolute );
		$size   = filesize( $absolute );

		return new StreamHandle(
			$absolute,
			$mime_type,
			false === $size ? 0 : (int) $size,
			$stream
		);
	}
}

/**
 * Stream handle for authorized controllers. No public URL is included.
 */
final class StreamHandle {

	/**
	 * @param resource $stream
	 */
	public function __construct(
		public readonly string $absolute_path,
		public readonly string $mime_type,
		public readonly int $byte_size,
		public readonly mixed $stream
	) {}

	public function close(): void {
		if ( is_resource( $this->stream ) ) {
			fclose( $this->stream );
		}
	}
}
