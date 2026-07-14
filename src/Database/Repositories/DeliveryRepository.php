<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Database\Repositories;

use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryStatus;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryType;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

final class DeliveryRepository extends AbstractRepository {

	public const COLUMNS = [
		'delivery_id',
		'project_id',
		'guest_id',
		'delivery_type',
		'idempotency_key',
		'recipient_hash',
		'status',
		'attempt_count',
		'scheduled_at_utc',
		'started_at_utc',
		'sent_at_utc',
		'failed_at_utc',
		'last_error_code',
		'last_error_message',
		'created_at_utc',
		'updated_at_utc',
	];

	private const FORMATS = [
		'delivery_id'   => '%d',
		'project_id'    => '%d',
		'guest_id'      => '%d',
		'attempt_count' => '%d',
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
			$this->tables->deliveries(),
			$data,
			$this->formats_for( $columns, self::FORMATS )
		);

		return false === $result ? 0 : (int) $this->wpdb->insert_id;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_by_idempotency_key( string $idempotency_key ): ?array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->deliveries() . ' WHERE idempotency_key = %s LIMIT 1',
			$idempotency_key
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_by_id( int $delivery_id ): ?array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->deliveries() . ' WHERE delivery_id = %d LIMIT 1',
			$delivery_id
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function update( int $delivery_id, array $data ): bool {
		$data = $this->filter_columns( $data, self::COLUMNS );
		unset( $data['delivery_id'] );
		$data['updated_at_utc'] = UtcDateTime::now();

		$columns = array_keys( $data );

		return false !== $this->wpdb->update(
			$this->tables->deliveries(),
			$data,
			[ 'delivery_id' => $delivery_id ],
			$this->formats_for( $columns, self::FORMATS ),
			[ '%d' ]
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_failures_for_project( int $project_id, int $limit = 20 ): array {
		$limit = max( 1, min( 100, $limit ) );
		$sql   = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->deliveries() . ' WHERE project_id = %d ORDER BY failed_at_utc DESC LIMIT %d',
			$project_id,
			$limit
		);

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_values(
			array_filter(
				$rows,
				static fn( array $row ): bool => in_array(
					(string) ( $row['status'] ?? '' ),
					[ DeliveryStatus::FAILED, DeliveryStatus::CANCELLED, DeliveryStatus::SKIPPED ],
					true
				)
			)
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_queued_reminders_for_project( int $project_id ): array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->deliveries() . ' WHERE project_id = %d AND delivery_type = %s',
			$project_id,
			DeliveryType::RSVP_REMINDER
		);

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_values(
			array_filter(
				$rows,
				static fn( array $row ): bool => DeliveryStatus::QUEUED === (string) ( $row['status'] ?? '' )
			)
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_by_project_and_status( int $project_id, string $status, ?string $delivery_type = null ): array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->deliveries() . ' WHERE project_id = %d',
			$project_id
		);

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_values(
			array_filter(
				$rows,
				static function ( array $row ) use ( $status, $delivery_type ): bool {
					if ( $status !== (string) ( $row['status'] ?? '' ) ) {
						return false;
					}

					if ( null !== $delivery_type && $delivery_type !== (string) ( $row['delivery_type'] ?? '' ) ) {
						return false;
					}

					return true;
				}
			)
		);
	}

	public function delete_by_project( int $project_id ): bool {
		return false !== $this->wpdb->delete(
			$this->tables->deliveries(),
			[ 'project_id' => $project_id ],
			[ '%d' ]
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_by_project( int $project_id ): array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->deliveries() . ' WHERE project_id = %d ORDER BY delivery_id ASC',
			$project_id
		);

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * @param list<int> $project_ids
	 * @return list<array<string, mixed>>
	 */
	public function list_for_projects( array $project_ids ): array {
		if ( [] === $project_ids ) {
			return [];
		}

		$rows = [];
		foreach ( $project_ids as $project_id ) {
			$rows = array_merge( $rows, $this->list_by_project( (int) $project_id ) );
		}

		return $rows;
	}

	public function anonymize_older_than( string $cutoff_utc ): int {
		$sql  = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->deliveries() . ' WHERE created_at_utc < %s',
			$cutoff_utc
		);
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $rows as $row ) {
			$delivery_id = (int) ( $row['delivery_id'] ?? 0 );
			if ( $delivery_id <= 0 ) {
				continue;
			}
			if ( '' === (string) ( $row['recipient_hash'] ?? '' ) && '' === (string) ( $row['last_error_message'] ?? '' ) ) {
				continue;
			}
			$this->update(
				$delivery_id,
				[
					'recipient_hash'     => '',
					'last_error_message' => '',
				]
			);
			++$count;
		}

		return $count;
	}
}
