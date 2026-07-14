<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Privacy;

use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestTokenService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoServiceFactory;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectHardDeleteService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectLifecycleAudit;
use PrikOgStreg\OnlineInvitations\Scheduling\RetentionScheduler;
use PrikOgStreg\OnlineInvitations\Storage\StorageCleanup;
use PrikOgStreg\OnlineInvitations\Storage\StorageRegistry;

/**
 * Wires privacy policy, export/erase tools, and retention schedulers.
 */
final class PrivacyRegistrar {

	public function __construct(
		private RepositoryRegistry $repositories,
		private StorageRegistry $storage
	) {}

	public function register(): void {
		$queue = new DeliveryQueueService( $this->repositories->deliveries() );
		$audit = new ProjectLifecycleAudit( $this->repositories->events() );
		$hard_delete = new ProjectHardDeleteService(
			$this->repositories->projects(),
			$queue,
			$audit,
			$this->storage->project_storage()
		);

		$guest_anonymizer = new GuestAnonymizer(
			$this->repositories->guests(),
			new GuestTokenService( $this->repositories->guests() ),
			PhotoServiceFactory::create( $this->repositories, $this->storage )
		);

		$exporter = new PersonalDataExporter(
			$this->repositories->projects(),
			$this->repositories->guests(),
			$this->repositories->address_book(),
			$this->repositories->wishlist_items(),
			$this->repositories->wishlist_reservations(),
			$this->repositories->photos(),
			$this->repositories->deliveries(),
			$this->repositories->events()
		);

		$eraser = new PersonalDataEraser(
			$this->repositories->projects(),
			$this->repositories->guests(),
			$this->repositories->address_book(),
			$guest_anonymizer,
			$hard_delete
		);

		( new Policy() )->register();
		( new ExporterRegistrar( $exporter ) )->register();
		( new EraserRegistrar( $eraser ) )->register();

		( new RetentionScheduler(
			$this->repositories->projects(),
			$this->repositories->deliveries(),
			$this->repositories->events(),
			new StorageCleanup( $this->storage->paths() )
		) )->register();
	}
}
