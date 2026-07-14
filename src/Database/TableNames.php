<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Database;

/**
 * Table name resolver.
 */
final class TableNames {

	public function __construct(
		private string $prefix
	) {}

	public function projects(): string {
		return $this->prefix . 'pks_oi_projects';
	}

	public function guests(): string {
		return $this->prefix . 'pks_oi_guests';
	}

	public function address_book(): string {
		return $this->prefix . 'pks_oi_address_book';
	}

	public function wishlist_items(): string {
		return $this->prefix . 'pks_oi_wishlist_items';
	}

	public function wishlist_reservations(): string {
		return $this->prefix . 'pks_oi_wishlist_reservations';
	}

	public function photos(): string {
		return $this->prefix . 'pks_oi_photos';
	}

	public function deliveries(): string {
		return $this->prefix . 'pks_oi_deliveries';
	}

	public function events(): string {
		return $this->prefix . 'pks_oi_events';
	}
}
