<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Database\Repositories;

use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

final class EventRepository extends AbstractRepository {

	public const COLUMNS = [
		'event_id',
		'project_id',
		'guest_id',
		'actor_type',
		'actor_id',
		'event_type',
		'metadata_json',
		'created_at_utc',
	];

	private const FORMATS = [
		'event_id'   => '%d',
		'project_id' => '%d',
		'guest_id'   => '%d',
		'actor_id'   => '%d',
	];

	/**
	 * @param array<string, mixed> $data
	 */
	public function insert( array $data ): int {
		$data = $this->filter_columns( $data, self::COLUMNS );
		$data['created_at_utc'] ??= UtcDateTime::now();

		$columns = array_keys( $data );
		$result  = $this->wpdb->insert(
			$this->tables->events(),
			$data,
			$this->formats_for( $columns, self::FORMATS )
		);

		return false === $result ? 0 : (int) $this->wpdb->insert_id;
	}

	public function delete_by_project( int $project_id ): bool {
		return false !== $this->wpdb->delete(
			$this->tables->events(),
			[ 'project_id' => $project_id ],
			[ '%d' ]
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_rsvp_events_for_project( int $project_id, int $limit = 20 ): array {
		$limit = max( 1, min( 100, $limit ) );
		$sql   = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->events() . ' WHERE project_id = %d ORDER BY created_at_utc DESC LIMIT %d',
			$project_id,
			$limit
		);

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return [];
		}

		$types = [ 'guest_rsvp_submitted', 'guest_rsvp_changed', 'generic_rsvp_created' ];

		return array_values(
			array_filter(
				$rows,
				static fn( array $row ): bool => in_array( (string) ( $row['event_type'] ?? '' ), $types, true )
			)
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_recent_for_project( int $project_id, int $limit = 20 ): array {
		$limit = max( 1, min( 100, $limit ) );
		$sql   = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->events() . ' WHERE project_id = %d ORDER BY created_at_utc DESC LIMIT %d',
			$project_id,
			$limit
		);

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}

	public function delete_older_than( string $cutoff_utc ): int {
		$sql  = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->events() . ' WHERE created_at_utc < %s',
			$cutoff_utc
		);
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $rows as $row ) {
			$event_id = (int) ( $row['event_id'] ?? 0 );
			if ( $event_id <= 0 ) {
				continue;
			}
			if ( false !== $this->wpdb->delete(
				$this->tables->events(),
				[ 'event_id' => $event_id ],
				[ '%d' ]
			) ) {
				++$count;
			}
		}

		return $count;
	}
}
