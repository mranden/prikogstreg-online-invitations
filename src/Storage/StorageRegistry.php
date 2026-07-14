<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Storage;

/**
 * Factory for storage services with optional test root override.
 */
final class StorageRegistry {

	private StoragePath $paths;

	private ?ProjectStorage $project_storage = null;

	public function __construct(
		?string $root_override = null
	) {
		$this->paths = new StoragePath( $root_override );
	}

	public function paths(): StoragePath {
		return $this->paths;
	}

	public function project_storage(): ProjectStorage {
		if ( null === $this->project_storage ) {
			$this->project_storage = new ProjectStorage(
				$this->paths,
				new AtomicFileWriter(),
				new SafeFileReader(),
				new StorageCleanup( $this->paths )
			);
		}

		return $this->project_storage;
	}

	public function diagnostic(): StorageDiagnostic {
		return new StorageDiagnostic(
			$this->paths,
			$this->project_storage(),
			new StorageCleanup( $this->paths )
		);
	}

	public function bootstrap(): StorageBootstrap {
		return new StorageBootstrap( $this->paths );
	}

	public function file_streams(): FileStreamResponse {
		return new FileStreamResponse( $this->paths, new SafeFileReader() );
	}
}
