<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Photo;

use PrikOgStreg\OnlineInvitations\Storage\AtomicFileWriter;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StoragePathException;
use PrikOgStreg\OnlineInvitations\Storage\ProjectStorage;
use PrikOgStreg\OnlineInvitations\Storage\StoragePath;

/**
 * Writes guest photos under private project storage.
 */
final class PhotoStorageService {

	public function __construct(
		private ProjectStorage $project_storage,
		private StoragePath $paths,
		private AtomicFileWriter $writer
	) {}

	/**
	 * @return array{success:bool,error?:string,relative_path?:string,bytes_written?:int}
	 */
	public function store_pending( string $storage_uuid, string $file_uuid, string $mime, string $bytes ): array {
		$extension = $this->extension_for_mime( $mime );
		$relative  = 'photos/pending/' . $file_uuid . '.' . $extension;

		return $this->write( $storage_uuid, $relative, $bytes );
	}

	/**
	 * @return array{success:bool,error?:string,relative_path?:string,bytes_written?:int}
	 */
	public function store_approved( string $storage_uuid, string $file_uuid, string $mime, string $bytes ): array {
		$extension = $this->extension_for_mime( $mime );
		$relative  = 'photos/approved/' . $file_uuid . '.' . $extension;

		return $this->write( $storage_uuid, $relative, $bytes );
	}

	/**
	 * @return array{success:bool,error?:string,relative_path?:string,bytes_written?:int}
	 */
	public function store_thumbnail( string $storage_uuid, string $relative_path, string $bytes ): array {
		return $this->write( $storage_uuid, $relative_path, $bytes );
	}

	public function delete_file( string $storage_uuid, string $relative_path ): void {
		try {
			$path = $this->paths->absolute_from_relative( $storage_uuid, $relative_path );
		} catch ( StoragePathException ) {
			return;
		}

		if ( is_file( $path ) ) {
			@unlink( $path );
		}
	}

	public function absolute_path( string $storage_uuid, string $relative_path ): ?string {
		try {
			return $this->paths->absolute_from_relative( $storage_uuid, $relative_path );
		} catch ( StoragePathException ) {
			return null;
		}
	}

	/**
	 * @return array{success:bool,error?:string,relative_path?:string,bytes_written?:int}
	 */
	private function write( string $storage_uuid, string $relative_path, string $bytes ): array {
		try {
			$this->paths->assert_relative_path( $relative_path );
		} catch ( StoragePathException ) {
			return [ 'success' => false, 'error' => 'invalid_path' ];
		}

		$this->project_storage->create_project_directories( $storage_uuid );
		$absolute = $this->paths->absolute_from_relative( $storage_uuid, $relative_path );

		$written = $this->writer->write( $absolute, $bytes );
		if ( ! $written ) {
			return [ 'success' => false, 'error' => 'write_failed' ];
		}

		return [
			'success'       => true,
			'relative_path' => $relative_path,
			'bytes_written' => strlen( $bytes ),
		];
	}

	private function extension_for_mime( string $mime ): string {
		return match ( $mime ) {
			'image/png'  => 'png',
			'image/webp' => 'webp',
			default      => 'jpg',
		};
	}
}
