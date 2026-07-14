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

/**
 * Factory for repository instances bound to the active $wpdb connection.
 */
final class RepositoryRegistry {

	private TableNames $tables;

	public function __construct(
		private object $wpdb
	) {
		$this->tables = new TableNames( (string) $wpdb->prefix );
	}

	public function projects(): ProjectRepository {
		return new ProjectRepository( $this->wpdb, $this->tables );
	}

	public function guests(): GuestRepository {
		return new GuestRepository( $this->wpdb, $this->tables );
	}

	public function address_book(): AddressBookRepository {
		return new AddressBookRepository( $this->wpdb, $this->tables );
	}

	public function wishlist_items(): WishlistItemRepository {
		return new WishlistItemRepository( $this->wpdb, $this->tables );
	}

	public function wishlist_reservations(): WishlistReservationRepository {
		return new WishlistReservationRepository( $this->wpdb, $this->tables );
	}

	public function photos(): PhotoRepository {
		return new PhotoRepository( $this->wpdb, $this->tables );
	}

	public function deliveries(): DeliveryRepository {
		return new DeliveryRepository( $this->wpdb, $this->tables );
	}

	public function events(): EventRepository {
		return new EventRepository( $this->wpdb, $this->tables );
	}
}
