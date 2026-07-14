<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin;

use PrikOgStreg\OnlineInvitations\Admin\Invitations\InvitationAdminActions;
use PrikOgStreg\OnlineInvitations\Admin\Invitations\InvitationDetailPage;
use PrikOgStreg\OnlineInvitations\Admin\Invitations\InvitationPreviewController;
use PrikOgStreg\OnlineInvitations\Admin\Invitations\InvitationsPage;
use PrikOgStreg\OnlineInvitations\Admin\Menu\AdminMenu;
use PrikOgStreg\OnlineInvitations\Admin\Photos\PhotosAdminPage;
use PrikOgStreg\OnlineInvitations\Admin\Settings\SettingsPage;
use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestService;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestTokenService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoServiceFactory;
use PrikOgStreg\OnlineInvitations\Domain\Project\GenericTokenService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectEventService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectHardDeleteService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectLifecycleAudit;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectPreviewService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectPublishService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectRestoreService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectRestrictionService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStateService;
use PrikOgStreg\OnlineInvitations\Public\PosterDisplayAssets;
use PrikOgStreg\OnlineInvitations\Public\PublicInvitationLoader;
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

		$list_view_model = new ProjectAdminListViewModel(
			$this->repositories->projects(),
			$this->repositories->guests(),
			$this->repositories->photos(),
			$this->templates
		);

		$preview = new ProjectPreviewService( $this->builder, $state );
		$published_loader = new PublicInvitationLoader(
			$this->storage->project_storage(),
			$this->builder,
			new PosterDisplayAssets( $this->storage->project_storage() )
		);

		$photo_service = PhotoServiceFactory::create( $this->repositories, $this->storage );
		$guest_service = new GuestService( $this->repositories->guests(), new GuestTokenService( $this->repositories->guests() ) );
		$support_service = new AdminSupportService(
			$this->repositories->projects(),
			$this->repositories->guests(),
			new ProjectEventService( $this->repositories->projects(), $this->repositories->events() ),
			$audit
		);

		( new ProjectSupportScreen( $view_model, $this->templates ) )->register();
		( new InvitationPreviewController(
			$this->repositories->projects(),
			$preview,
			$published_loader
		) )->register();
		( new InvitationAdminActions(
			$this->repositories->projects(),
			$this->repositories->guests(),
			$support_service,
			$photo_service,
			$audit
		) )->register();

		$settings = new SettingsPage( $this->templates );
		$settings->register();

		( new AdminMenu(
			new InvitationsPage( $list_view_model, new InvitationDetailPage( $view_model, $guest_service, $photo_service, $this->templates ), $this->templates ),
			new PhotosAdminPage( $this->repositories->photos(), $this->repositories->projects(), $this->templates ),
			$settings
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
