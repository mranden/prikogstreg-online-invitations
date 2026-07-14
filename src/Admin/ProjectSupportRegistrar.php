<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Domain\Project\GenericTokenService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectHardDeleteService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectLifecycleAudit;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectPublishService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectRestoreService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectRestrictionService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStateService;
use PrikOgStreg\OnlineInvitations\Scheduling\WelcomeScheduler;
use PrikOgStreg\OnlineInvitations\Storage\StorageRegistry;
use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;
use PrikOgStreg\OnlineInvitations\WooCommerce\Orders\OrderRefundDetector;

/**
 * Wires admin support dashboard and lifecycle actions.
 */
final class ProjectSupportRegistrar {

	public function __construct(
		private RepositoryRegistry $repositories,
		private BuilderService $builder,
		private StorageRegistry $storage,
		private TemplateLoader $templates
	) {}

	public function register(): void {
		$queue = new DeliveryQueueService( $this->repositories->deliveries() );
		$audit = new ProjectLifecycleAudit( $this->repositories->events() );
		$state = new ProjectStateService(
			$this->builder,
			$this->storage->project_storage(),
			$this->repositories->projects(),
			$this->repositories->events()
		);

		$view_model = new ProjectSupportViewModel(
			$this->repositories->projects(),
			$this->repositories->guests(),
			$this->repositories->wishlist_items(),
			$this->repositories->photos(),
			$this->repositories->deliveries(),
			$this->repositories->events(),
			$this->storage->diagnostic()
		);

		( new ProjectSupportScreen( $view_model, $this->templates ) )->register();
		( new ProjectsAdminScreen(
			new ProjectAdminListViewModel( $this->repositories->projects() ),
			$view_model,
			$this->templates
		) )->register();
		( new AdminAssets() )->register();

		$welcome = new WelcomeScheduler(
			$this->repositories->projects(),
			$this->repositories->deliveries(),
			$queue
		);

		( new ProjectSupportActions(
			$this->repositories->projects(),
			new ProjectRestrictionService( $this->repositories->projects(), $queue, $audit ),
			new ProjectRestoreService(
				$this->repositories->projects(),
				new OrderRefundDetector(),
				$audit
			),
			new ProjectPublishService(
				$this->builder,
				$this->storage->project_storage(),
				$this->repositories->projects(),
				$state,
				$this->repositories->events()
			),
			new GenericTokenService( $this->repositories->projects() ),
			$welcome,
			new ProjectHardDeleteService(
				$this->repositories->projects(),
				$queue,
				$audit,
				$this->storage->project_storage()
			)
		) )->register();
	}
}
