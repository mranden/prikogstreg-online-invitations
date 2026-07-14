<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Database\Repositories;

use PrikOgStreg\OnlineInvitations\Domain\Wishlist\WishlistItemStatus;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

final class WishlistItemRepository extends AbstractRepository {

	public const COLUMNS = [
		'wishlist_item_id',
		'project_id',
		'title',
		'description',
		'external_url',
		'image_path',
		'quantity_requested',
		'quantity_reserved',
		'sort_order',
		'status',
		'created_at_utc',
		'updated_at_utc',
	];

	private const FORMATS = [
		'wishlist_item_id'   => '%d',
		'project_id'         => '%d',
		'quantity_requested' => '%d',
		'quantity_reserved'  => '%d',
		'sort_order'         => '%d',
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
			$this->tables->wishlist_items(),
			$data,
			$this->formats_for( $columns, self::FORMATS )
		);

		return false === $result ? 0 : (int) $this->wpdb->insert_id;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function update( int $wishlist_item_id, array $data ): bool {
		$data = $this->filter_columns( $data, self::COLUMNS );
		unset( $data['wishlist_item_id'] );
		$data['updated_at_utc'] = UtcDateTime::now();

		$columns = array_keys( $data );

		return false !== $this->wpdb->update(
			$this->tables->wishlist_items(),
			$data,
			[ 'wishlist_item_id' => $wishlist_item_id ],
			$this->formats_for( $columns, self::FORMATS ),
			[ '%d' ]
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_by_id( int $wishlist_item_id ): ?array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->wishlist_items() . ' WHERE wishlist_item_id = %d LIMIT 1',
			$wishlist_item_id
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_by_id_for_project( int $wishlist_item_id, int $project_id ): ?array {
		$row = $this->find_by_id( $wishlist_item_id );
		if ( ! is_array( $row ) || (int) ( $row['project_id'] ?? 0 ) !== $project_id ) {
			return null;
		}

		return $row;
	}

	/**
	 * @param list<string>|null $statuses
	 * @return list<array<string, mixed>>
	 */
	public function list_for_project( int $project_id, ?array $statuses = null ): array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->wishlist_items() . ' WHERE project_id = %d ORDER BY sort_order ASC, wishlist_item_id ASC',
			$project_id
		);

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return [];
		}

		if ( null === $statuses ) {
			return array_values( $rows );
		}

		return array_values(
			array_filter(
				$rows,
				static fn( array $row ): bool => in_array( (string) ( $row['status'] ?? '' ), $statuses, true )
			)
		);
	}

	public function try_adjust_reserved( int $wishlist_item_id, int $delta, int $expected_reserved ): bool {
		$row = $this->find_by_id( $wishlist_item_id );
		if ( ! is_array( $row ) ) {
			return false;
		}

		$current   = (int) ( $row['quantity_reserved'] ?? 0 );
		$requested = (int) ( $row['quantity_requested'] ?? 0 );
		if ( $current !== $expected_reserved ) {
			return false;
		}

		$new = $current + $delta;
		if ( $new < 0 || $new > $requested ) {
			return false;
		}

		$updated = $this->wpdb->update(
			$this->tables->wishlist_items(),
			[
				'quantity_reserved' => $new,
				'updated_at_utc'    => UtcDateTime::now(),
			],
			[
				'wishlist_item_id'  => $wishlist_item_id,
				'quantity_reserved' => $expected_reserved,
			],
			[ '%d', '%s' ],
			[ '%d', '%d' ]
		);

		return false !== $updated && $updated > 0;
	}

	public function delete_by_project( int $project_id ): bool {
		return false !== $this->wpdb->delete(
			$this->tables->wishlist_items(),
			[ 'project_id' => $project_id ],
			[ '%d' ]
		);
	}
}
