<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Scheduling;

use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryRecipientResolver;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliverySendService;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestTokenService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectExpireService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectLifecycleAudit;

/**
 * Registers Action Scheduler delivery handlers and reminder scheduling.
 */
final class SchedulerRegistrar {

	public function __construct(
		private RepositoryRegistry $repositories
	) {}

	public function register(): void {
		$queue = new DeliveryQueueService( $this->repositories->deliveries() );
		$sender = new DeliverySendService(
			$this->repositories->deliveries(),
			new DeliveryRecipientResolver( $this->repositories->projects(), $this->repositories->guests() ),
			$this->repositories->guests(),
			new GuestTokenService( $this->repositories->guests() )
		);

		( new DeliveryActionHandler( $sender ) )->register();
		( new ReminderScheduler(
			$this->repositories->projects(),
			$this->repositories->guests(),
			$queue
		) )->register();

		$expire = new ProjectExpireService(
			$this->repositories->projects(),
			$queue,
			new \PrikOgStreg\OnlineInvitations\Domain\Project\ProjectLifecycleAudit( $this->repositories->events() )
		);
		( new ExpirationScheduler( $this->repositories->projects(), $expire ) )->register();
	}
}
