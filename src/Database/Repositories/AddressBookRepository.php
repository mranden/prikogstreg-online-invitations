<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Database\Repositories;

use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

final class AddressBookRepository extends AbstractRepository {

	public const COLUMNS = [
		'address_book_id',
		'user_id',
		'display_name',
		'email',
		'phone',
		'notes',
		'normalized_email_hash',
		'created_at_utc',
		'updated_at_utc',
		'archived_at_utc',
	];

	private const FORMATS = [
		'address_book_id' => '%d',
		'user_id'         => '%d',
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
			$this->tables->address_book(),
			$data,
			$this->formats_for( $columns, self::FORMATS )
		);

		return false === $result ? 0 : (int) $this->wpdb->insert_id;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function update( int $address_book_id, array $data ): bool {
		$data = $this->filter_columns( $data, self::COLUMNS );
		unset( $data['address_book_id'] );
		$data['updated_at_utc'] = UtcDateTime::now();

		$columns = array_keys( $data );

		return false !== $this->wpdb->update(
			$this->tables->address_book(),
			$data,
			[ 'address_book_id' => $address_book_id ],
			$this->formats_for( $columns, self::FORMATS ),
			[ '%d' ]
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_by_id_for_user( int $address_book_id, int $user_id ): ?array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->address_book() . ' WHERE address_book_id = %d AND user_id = %d LIMIT 1',
			$address_book_id,
			$user_id
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	public function delete_for_user( int $address_book_id, int $user_id ): bool {
		$contact = $this->find_by_id_for_user( $address_book_id, $user_id );
		if ( ! is_array( $contact ) ) {
			return false;
		}

		return false !== $this->wpdb->delete(
			$this->tables->address_book(),
			[
				'address_book_id' => $address_book_id,
				'user_id'         => $user_id,
			],
			[ '%d', '%d' ]
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_by_normalized_email_hash( int $user_id, string $hash ): ?array {
		if ( '' === $hash ) {
			return null;
		}

		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->address_book() . ' WHERE user_id = %d AND normalized_email_hash = %s AND archived_at_utc IS NULL LIMIT 1',
			$user_id,
			$hash
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int}
	 */
	public function list_for_user( int $user_id, int $page, int $per_page, string $search = '' ): array {
		$page     = max( 1, $page );
		$per_page = max( 1, min( 100, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;
		$total    = $this->count_for_user( $user_id, $search );

		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->address_book() . ' WHERE user_id = %d AND archived_at_utc IS NULL ORDER BY display_name ASC LIMIT %d OFFSET %d',
			$user_id,
			$per_page,
			$offset
		);

		$items = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( '' !== $search && is_array( $items ) ) {
			$needle = strtolower( $search );
			$items  = array_values(
				array_filter(
					$items,
					static fn( array $row ): bool => str_contains( strtolower( (string) ( $row['display_name'] ?? '' ) ), $needle )
						|| str_contains( strtolower( (string) ( $row['email'] ?? '' ) ), $needle )
				)
			);
		}

		return [
			'items'    => is_array( $items ) ? $items : [],
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		];
	}

	public function count_for_user( int $user_id, string $search = '' ): int {
		$sql  = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->address_book() . ' WHERE user_id = %d AND archived_at_utc IS NULL',
			$user_id
		);
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return 0;
		}

		if ( '' === $search ) {
			return count( $rows );
		}

		$needle = strtolower( $search );
		$count  = 0;
		foreach ( $rows as $row ) {
			if (
				str_contains( strtolower( (string) ( $row['display_name'] ?? '' ) ), $needle )
				|| str_contains( strtolower( (string) ( $row['email'] ?? '' ) ), $needle )
			) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_active_for_user( int $user_id ): array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->address_book() . ' WHERE user_id = %d AND archived_at_utc IS NULL ORDER BY display_name ASC',
			$user_id
		);

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}

	public function delete_all_for_user( int $user_id ): int {
		$contacts = $this->list_active_for_user( $user_id );
		$deleted  = 0;
		foreach ( $contacts as $contact ) {
			$id = (int) ( $contact['address_book_id'] ?? 0 );
			if ( $id > 0 && $this->delete_for_user( $id, $user_id ) ) {
				++$deleted;
			}
		}

		return $deleted;
	}
}
