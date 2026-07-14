<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Database\Repositories;

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
}
