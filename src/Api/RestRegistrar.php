<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Api;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Domain\Project\DemoInvitationService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectEventService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectPublishService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStateService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoServiceFactory;
use PrikOgStreg\OnlineInvitations\Domain\Rsvp\RsvpService;
use PrikOgStreg\OnlineInvitations\Domain\Wishlist\WishlistReservationService;
use PrikOgStreg\OnlineInvitations\Public\PhotoController;
use PrikOgStreg\OnlineInvitations\Public\RsvpController;
use PrikOgStreg\OnlineInvitations\Public\RsvpRateLimiter;
use PrikOgStreg\OnlineInvitations\Public\TokenResolver;
use PrikOgStreg\OnlineInvitations\Public\WishlistController;
use PrikOgStreg\OnlineInvitations\Public\WishlistRateLimiter;
use PrikOgStreg\OnlineInvitations\Security\Authorization;
use PrikOgStreg\OnlineInvitations\Storage\StorageRegistry;

/**
 * Registers authenticated REST routes.
 */
final class RestRegistrar {

	public function __construct(
		private RepositoryRegistry $repositories,
		private BuilderService $builder,
		private StorageRegistry $storage
	) {}

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		$authorization = new Authorization( $this->repositories->projects() );
		$state_service = new ProjectStateService(
			$this->builder,
			$this->storage->project_storage(),
			$this->repositories->projects(),
			$this->repositories->events()
		);
		$publish_service = new ProjectPublishService(
			$this->builder,
			$this->storage->project_storage(),
			$this->repositories->projects(),
			$state_service,
			$this->repositories->events()
		);

		$queue = new DeliveryQueueService( $this->repositories->deliveries() );

		( new ProjectRestController(
			$authorization,
			$state_service,
			new ProjectEventService( $this->repositories->projects(), $this->repositories->events() ),
			$publish_service,
			new DemoInvitationService( $this->repositories->events(), $queue )
		) )->register_routes();

		$rsvp = new RsvpService(
			$this->repositories->guests(),
			$this->repositories->events(),
			new DeliveryQueueService( $this->repositories->deliveries() )
		);

		( new RsvpController(
			new TokenResolver( $this->repositories->guests(), $this->repositories->projects() ),
			$rsvp,
			new RsvpRateLimiter()
		) )->register_routes();

		$wishlist = new WishlistReservationService(
			$this->repositories->wishlist_items(),
			$this->repositories->wishlist_reservations(),
			$this->repositories->guests(),
			$this->repositories->events()
		);

		( new WishlistController(
			new TokenResolver( $this->repositories->guests(), $this->repositories->projects() ),
			$wishlist,
			new WishlistRateLimiter()
		) )->register_routes();

		( new PhotoController(
			new TokenResolver( $this->repositories->guests(), $this->repositories->projects() ),
			PhotoServiceFactory::create( $this->repositories, $this->storage )
		) )->register_routes();
	}
}
