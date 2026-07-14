<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Database;

use PrikOgStreg\OnlineInvitations\Database\Repositories\AddressBookRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\DeliveryRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\EventRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\PhotoRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\WishlistItemRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\WishlistReservationRepository;
use PrikOgStreg\OnlineInvitations\Storage\ProjectStorage;

/**
 * Removes all custom-table rows for a project when its CPT shell is deleted.
 */
final class ProjectDomainCleanup {

	public function __construct(
		private ProjectRepository $projects,
		private GuestRepository $guests,
		private WishlistItemRepository $wishlist_items,
		private WishlistReservationRepository $wishlist_reservations,
		private PhotoRepository $photos,
		private DeliveryRepository $deliveries,
		private EventRepository $events,
		private ProjectStorage $project_storage
	) {}

	public function register(): void {
		add_action( 'pks_oi_before_project_domain_cleanup', [ $this, 'cleanup' ] );
	}

	public function cleanup( int $project_id ): void {
		$project = $this->projects->find_by_id( $project_id );

		if ( is_array( $project ) && isset( $project['storage_uuid'] ) ) {
			$this->project_storage->delete_project_storage( (string) $project['storage_uuid'] );
		}

		$this->wishlist_reservations->delete_by_project( $project_id );
		$this->wishlist_items->delete_by_project( $project_id );
		$this->guests->delete_by_project( $project_id );
		$this->photos->delete_by_project( $project_id );
		$this->deliveries->delete_by_project( $project_id );
		$this->events->delete_by_project( $project_id );
		$this->projects->delete_by_id( $project_id );
	}
}
