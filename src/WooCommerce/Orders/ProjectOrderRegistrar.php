<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\Orders;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectFactory;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectService;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectLifecycleAudit;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectRestrictionService;
use PrikOgStreg\OnlineInvitations\Scheduling\WelcomeScheduler;
use PrikOgStreg\OnlineInvitations\Storage\StorageRegistry;
use PrikOgStreg\OnlineInvitations\WooCommerce\Orders\OrderRefundDetector;
use PrikOgStreg\OnlineInvitations\WooCommerce\Orders\ProjectRefundListener;

/**
 * Registers order-status project creation.
 */
final class ProjectOrderRegistrar {

	private ProjectService $service;

	private RepositoryRegistry $repositories;

	public function __construct(
		RepositoryRegistry $repositories,
		BuilderService $builder,
		StorageRegistry $storage
	) {
		$this->repositories = $repositories;
		$welcome = new WelcomeScheduler(
			$repositories->projects(),
			$repositories->deliveries(),
			new DeliveryQueueService( $repositories->deliveries() )
		);
		$welcome->register();

		$this->service = new ProjectService(
			$repositories->projects(),
			$repositories->events(),
			new ProjectFactory(),
			$builder,
			$storage->project_storage(),
			$welcome
		);
	}

	public function register(): void {
		( new ProjectOrderListener( $this->service ) )->register();

		$queue = new DeliveryQueueService( $this->repositories->deliveries() );
		( new ProjectRefundListener(
			$this->repositories->projects(),
			new OrderRefundDetector(),
			new ProjectRestrictionService(
				$this->repositories->projects(),
				$queue,
				new ProjectLifecycleAudit( $this->repositories->events() )
			)
		) )->register();
	}

	public function projects(): ProjectService {
		return $this->service;
	}
}
