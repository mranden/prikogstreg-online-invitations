<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Database\Repositories;

use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoModerationStatus;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

final class PhotoRepository extends AbstractRepository {

	public const COLUMNS = [
		'photo_id',
		'project_id',
		'guest_id',
		'storage_uuid',
		'relative_path',
		'thumbnail_path',
		'original_filename',
		'mime_type',
		'byte_size',
		'width',
		'height',
		'sha256',
		'moderation_status',
		'caption',
		'created_at_utc',
		'moderated_at_utc',
		'deleted_at_utc',
	];

	private const FORMATS = [
		'photo_id'   => '%d',
		'project_id' => '%d',
		'guest_id'   => '%d',
		'byte_size'  => '%d',
		'width'      => '%d',
		'height'     => '%d',
	];

	/**
	 * @param array<string, mixed> $data
	 */
	public function insert( array $data ): int {
		$data = $this->filter_columns( $data, self::COLUMNS );
		$data['created_at_utc'] ??= UtcDateTime::now();

		$columns = array_keys( $data );
		$result  = $this->wpdb->insert(
			$this->tables->photos(),
			$data,
			$this->formats_for( $columns, self::FORMATS )
		);

		return false === $result ? 0 : (int) $this->wpdb->insert_id;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_by_id( int $photo_id ): ?array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->photos() . ' WHERE photo_id = %d LIMIT 1',
			$photo_id
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_by_id_for_project( int $photo_id, int $project_id ): ?array {
		$row = $this->find_by_id( $photo_id );
		if ( ! is_array( $row ) || (int) ( $row['project_id'] ?? 0 ) !== $project_id ) {
			return null;
		}

		return $row;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_for_project( int $project_id, ?string $status = null, bool $include_deleted = false ): array {
		$table = $this->tables->photos();
		$sql   = 'SELECT * FROM ' . $table . ' WHERE project_id = %d';
		$args  = [ $project_id ];

		if ( ! $include_deleted ) {
			$sql .= ' AND deleted_at_utc IS NULL';
		}

		if ( null !== $status && '' !== $status ) {
			$sql   .= ' AND moderation_status = %s';
			$args[] = $status;
		}

		$sql .= ' ORDER BY created_at_utc DESC';

		$prepared = $this->wpdb->prepare( $sql, ...$args );
		$rows     = $this->wpdb->get_results( $prepared, ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int}
	 */
	public function list_paginated_for_project(
		int $project_id,
		string $status,
		int $page = 1,
		int $per_page = 20
	): array {
		$page     = max( 1, $page );
		$per_page = max( 1, min( 50, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;
		$table    = $this->tables->photos();

		$count_sql = $this->wpdb->prepare(
			'SELECT COUNT(*) FROM ' . $table . ' WHERE project_id = %d AND deleted_at_utc IS NULL AND moderation_status = %s',
			$project_id,
			$status
		);
		$total = (int) $this->wpdb->get_var( $count_sql );

		$list_sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $table . ' WHERE project_id = %d AND deleted_at_utc IS NULL AND moderation_status = %s ORDER BY created_at_utc DESC LIMIT %d OFFSET %d',
			$project_id,
			$status,
			$per_page,
			$offset
		);
		$rows = $this->wpdb->get_results( $list_sql, ARRAY_A );

		return [
			'items'    => is_array( $rows ) ? $rows : [],
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		];
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function update( int $photo_id, array $data ): bool {
		$data = $this->filter_columns( $data, self::COLUMNS );
		unset( $data['photo_id'] );

		if ( [] === $data ) {
			return false;
		}

		$columns = array_keys( $data );

		return false !== $this->wpdb->update(
			$this->tables->photos(),
			$data,
			[ 'photo_id' => $photo_id ],
			$this->formats_for( $columns, self::FORMATS ),
			[ '%d' ]
		);
	}

	public function soft_delete( int $photo_id ): bool {
		return $this->update(
			$photo_id,
			[
				'deleted_at_utc' => UtcDateTime::now(),
			]
		);
	}

	public function sum_active_bytes( int $project_id ): int {
		$sql = $this->wpdb->prepare(
			'SELECT COALESCE(SUM(byte_size), 0) FROM ' . $this->tables->photos()
			. ' WHERE project_id = %d AND deleted_at_utc IS NULL',
			$project_id
		);

		return (int) $this->wpdb->get_var( $sql );
	}

	/**
	 * @return list<string>
	 */
	public function list_relative_paths_for_project( string $storage_uuid ): array {
		$sql = $this->wpdb->prepare(
			'SELECT relative_path, thumbnail_path FROM ' . $this->tables->photos()
			. ' WHERE storage_uuid = %s AND deleted_at_utc IS NULL',
			$storage_uuid
		);
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return [];
		}

		$paths = [];
		foreach ( $rows as $row ) {
			$relative = (string) ( $row['relative_path'] ?? '' );
			if ( '' !== $relative ) {
				$paths[] = $relative;
			}
			$thumb = (string) ( $row['thumbnail_path'] ?? '' );
			if ( '' !== $thumb ) {
				$paths[] = $thumb;
			}
		}

		return $paths;
	}

	public function delete_by_project( int $project_id ): bool {
		return false !== $this->wpdb->delete(
			$this->tables->photos(),
			[ 'project_id' => $project_id ],
			[ '%d' ]
		);
	}

	public function delete_by_guest( int $guest_id ): int {
		return (int) $this->wpdb->delete(
			$this->tables->photos(),
			[ 'guest_id' => $guest_id ],
			[ '%d' ]
		);
	}

	/**
	 * @param list<int> $project_ids
	 * @return array<int, array{pending:int,approved:int,rejected:int,total:int}>
	 */
	public function batch_moderation_counts( array $project_ids ): array {
		$project_ids = array_values( array_filter( array_map( 'intval', $project_ids ) ) );
		$empty       = [];
		foreach ( $project_ids as $project_id ) {
			$empty[ $project_id ] = [
				'pending'  => 0,
				'approved' => 0,
				'rejected' => 0,
				'total'    => 0,
			];
		}

		if ( [] === $project_ids ) {
			return $empty;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $project_ids ), '%d' ) );
		$sql          = $this->wpdb->prepare(
			'SELECT project_id, moderation_status FROM ' . $this->tables->photos()
			. " WHERE project_id IN ({$placeholders}) AND deleted_at_utc IS NULL",
			...$project_ids
		);
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return $empty;
		}

		foreach ( $rows as $row ) {
			$project_id = (int) ( $row['project_id'] ?? 0 );
			$status     = (string) ( $row['moderation_status'] ?? '' );
			if ( ! isset( $empty[ $project_id ] ) ) {
				continue;
			}

			if ( isset( $empty[ $project_id ][ $status ] ) ) {
				++$empty[ $project_id ][ $status ];
			}
			++$empty[ $project_id ]['total'];
		}

		return $empty;
	}

	public function count_admin_pending(): int {
		$sql = $this->wpdb->prepare(
			'SELECT COUNT(*) FROM ' . $this->tables->photos()
			. ' WHERE deleted_at_utc IS NULL AND moderation_status = %s',
			PhotoModerationStatus::PENDING
		);

		return (int) $this->wpdb->get_var( $sql );
	}

	/**
	 * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int}
	 */
	public function list_admin_pending( int $page = 1, int $per_page = 20 ): array {
		$page     = max( 1, $page );
		$per_page = max( 1, min( 50, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;
		$table    = $this->tables->photos();

		$count_sql = $this->wpdb->prepare(
			'SELECT COUNT(*) FROM ' . $table . ' WHERE deleted_at_utc IS NULL AND moderation_status = %s',
			PhotoModerationStatus::PENDING
		);
		$total = (int) $this->wpdb->get_var( $count_sql );

		$list_sql = $this->wpdb->prepare(
			'SELECT photo_id, project_id, guest_id, original_filename, moderation_status, created_at_utc FROM ' . $table
			. ' WHERE deleted_at_utc IS NULL AND moderation_status = %s ORDER BY created_at_utc ASC LIMIT %d OFFSET %d',
			PhotoModerationStatus::PENDING,
			$per_page,
			$offset
		);
		$rows = $this->wpdb->get_results( $list_sql, ARRAY_A );

		return [
			'items'    => is_array( $rows ) ? $rows : [],
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		];
	}
}
