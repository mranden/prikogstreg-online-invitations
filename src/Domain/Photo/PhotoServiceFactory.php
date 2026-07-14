<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Photo;

use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Storage\AtomicFileWriter;
use PrikOgStreg\OnlineInvitations\Storage\StorageRegistry;

/**
 * Wires photo domain services for registrars.
 */
final class PhotoServiceFactory {

	public static function create( RepositoryRegistry $repositories, StorageRegistry $storage ): PhotoService {
		$paths = $storage->paths();

		return new PhotoService(
			$repositories->photos(),
			$repositories->projects(),
			$repositories->guests(),
			$repositories->events(),
			new PhotoUploadIntentService(),
			new PhotoShareUploadIntentService(),
			new PhotoImageValidator(),
			new PhotoImageProcessor(),
			new PhotoStorageService(
				$storage->project_storage(),
				$paths,
				new AtomicFileWriter()
			),
			new PhotoCleanupService( $paths, $repositories->photos() ),
			new DeliveryQueueService( $repositories->deliveries() ),
			new PhotoUploadRateLimiter()
		);
	}
}
