<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Database\Repositories;

use PrikOgStreg\OnlineInvitations\Domain\Wishlist\WishlistReservationStatus;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

final class WishlistReservationRepository extends AbstractRepository {

	public const COLUMNS = [
		'reservation_id',
		'wishlist_item_id',
		'project_id',
		'guest_id',
		'quantity',
		'status',
		'created_at_utc',
		'updated_at_utc',
		'released_at_utc',
	];

	private const FORMATS = [
		'reservation_id'   => '%d',
		'wishlist_item_id' => '%d',
		'project_id'       => '%d',
		'guest_id'         => '%d',
		'quantity'         => '%d',
	];

	/**
	 * @param array<string, mixed> $data
	 */
	public function insert( array $data ): int {
		$data = $this->filter_columns( $data, self::COLUMNS );
		$now  = UtcDateTime::now();
		$data['created_at_utc'] ??= $now;
		$data['updated_at_utc'] ??= $now;

		$columns = array_keys( $data );
		$result  = $this->wpdb->insert(
			$this->tables->wishlist_reservations(),
			$data,
			$this->formats_for( $columns, self::FORMATS )
		);

		return false === $result ? 0 : (int) $this->wpdb->insert_id;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function update( int $reservation_id, array $data ): bool {
		$data = $this->filter_columns( $data, self::COLUMNS );
		unset( $data['reservation_id'] );
		$data['updated_at_utc'] = UtcDateTime::now();

		$columns = array_keys( $data );

		return false !== $this->wpdb->update(
			$this->tables->wishlist_reservations(),
			$data,
			[ 'reservation_id' => $reservation_id ],
			$this->formats_for( $columns, self::FORMATS ),
			[ '%d' ]
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_by_id( int $reservation_id ): ?array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->wishlist_reservations() . ' WHERE reservation_id = %d LIMIT 1',
			$reservation_id
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_active_for_guest_item( int $wishlist_item_id, int $guest_id ): ?array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->wishlist_reservations() . ' WHERE wishlist_item_id = %d AND guest_id = %d',
			$wishlist_item_id,
			$guest_id
		);

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return null;
		}

		foreach ( $rows as $row ) {
			if ( WishlistReservationStatus::ACTIVE === (string) ( $row['status'] ?? '' ) ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_active_for_item( int $wishlist_item_id ): array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->wishlist_reservations() . ' WHERE wishlist_item_id = %d',
			$wishlist_item_id
		);

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_values(
			array_filter(
				$rows,
				static fn( array $row ): bool => WishlistReservationStatus::ACTIVE === (string) ( $row['status'] ?? '' )
			)
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_active_for_project( int $project_id ): array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->wishlist_reservations() . ' WHERE project_id = %d',
			$project_id
		);

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_values(
			array_filter(
				$rows,
				static fn( array $row ): bool => WishlistReservationStatus::ACTIVE === (string) ( $row['status'] ?? '' )
			)
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_for_guest( int $guest_id ): array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->wishlist_reservations() . ' WHERE guest_id = %d ORDER BY reservation_id ASC',
			$guest_id
		);

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}

	public function delete_by_project( int $project_id ): bool {
		return false !== $this->wpdb->delete(
			$this->tables->wishlist_reservations(),
			[ 'project_id' => $project_id ],
			[ '%d' ]
		);
	}
}
