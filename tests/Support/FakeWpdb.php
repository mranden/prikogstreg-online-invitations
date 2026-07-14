<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Support;

/**
 * In-memory $wpdb test double with unique-key enforcement.
 */
final class FakeWpdb {

	public string $prefix = 'wp_';

	public int $insert_id = 0;

	public string $last_error = '';

	/** @var array<string, list<array<string, mixed>>> */
	private array $tables = [];

	/** @var array<string, list<list<string>>> */
	private array $unique_keys = [
		'wp_pks_oi_projects' => [
			[ 'storage_uuid' ],
			[ 'order_item_id' ],
			[ 'generic_token_hash' ],
		],
		'wp_pks_oi_guests' => [
			[ 'token_hash' ],
		],
		'wp_pks_oi_photos' => [
			[ 'storage_uuid', 'relative_path' ],
		],
		'wp_pks_oi_deliveries' => [
			[ 'idempotency_key' ],
		],
		'wp_pks_oi_wishlist_reservations' => [
			[ 'wishlist_item_id', 'guest_id' ],
		],
	];

	public function get_charset_collate(): string {
		return 'DEFAULT CHARSET=utf8mb4';
	}

	/**
	 * @param array<string, mixed> $data
	 * @param list<string>         $format
	 */
	public function insert( string $table, array $data, $format = null ): int|false {
		if ( ! $this->assert_unique( $table, $data ) ) {
			$this->last_error = 'Duplicate entry';

			return false;
		}

		$this->tables[ $table ]   ??= [];
		$this->insert_id            = count( $this->tables[ $table ] ) + 1;
		$auto_key                   = $this->auto_increment_key( $table );

		if ( null !== $auto_key && ! isset( $data[ $auto_key ] ) ) {
			$data[ $auto_key ] = $this->insert_id;
		}

		$this->tables[ $table ][] = $data;
		$this->last_error         = '';

		return 1;
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $where
	 * @param list<string>         $format
	 * @param list<string>         $where_format
	 */
	public function update( string $table, array $data, array $where, $format = null, $where_format = null ): int|false {
		$updated = 0;

		foreach ( $this->tables[ $table ] ?? [] as $index => $row ) {
			if ( ! $this->row_matches( $row, $where ) ) {
				continue;
			}

			$merged = array_merge( $row, $data );
			if ( ! $this->assert_unique( $table, $merged, $index ) ) {
				$this->last_error = 'Duplicate entry';

				return false;
			}

			$this->tables[ $table ][ $index ] = $merged;
			++$updated;
		}

		$this->last_error = '';

		return $updated;
	}

	/**
	 * @param array<string, mixed> $where
	 * @param list<string>         $where_format
	 */
	public function delete( string $table, array $where, $where_format = null ): int|false {
		$before = count( $this->tables[ $table ] ?? [] );
		$this->tables[ $table ] = array_values(
			array_filter(
				$this->tables[ $table ] ?? [],
				fn( array $row ): bool => ! $this->row_matches( $row, $where )
			)
		);

		return $before - count( $this->tables[ $table ] );
	}

	public function get_var( string $query ) {
		if ( preg_match( '/COUNT\(\*\)/i', $query ) ) {
			$rows = $this->get_results( $query, ARRAY_A );

			return count( $rows );
		}

		$row = $this->get_row( $query, ARRAY_A );

		if ( ! is_array( $row ) ) {
			return null;
		}

		return reset( $row );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_row( string $query, $output = OBJECT, $y = 0 ) {
		$rows = $this->get_results( $query, $output );

		return $rows[0] ?? null;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function get_results( string $query, $output = OBJECT ): array {
		$table = $this->table_from_query( $query );
		$rows  = $this->tables[ $table ] ?? [];

		if ( preg_match( '/WHERE\s+(\w+)\s*=\s*\'([^\']*)\'/i', $query, $matches ) ) {
			$rows = array_values(
				array_filter(
					$rows,
					static fn( array $row ): bool => (string) ( $row[ $matches[1] ] ?? '' ) === $matches[2]
				)
			);
		} elseif ( preg_match( '/\bAND\s+(\w+)\s*=\s*\'([^\']*)\'/i', $query, $matches ) ) {
			$rows = array_values(
				array_filter(
					$rows,
					static fn( array $row ): bool => (string) ( $row[ $matches[1] ] ?? '' ) === $matches[2]
				)
			);
		} elseif ( preg_match( '/WHERE\s+(\w+)\s*<\s*\'([^\']*)\'/i', $query, $matches ) ) {
			$rows = array_values(
				array_filter(
					$rows,
					static fn( array $row ): bool => (string) ( $row[ $matches[1] ] ?? '' ) < $matches[2]
				)
			);
		} elseif ( preg_match( '/WHERE\s+(\w+)\s*=\s*(\d+)\s+AND\s+(\w+)\s*=\s*(\d+)/i', $query, $matches ) ) {
			$rows = array_values(
				array_filter(
					$rows,
					static fn( array $row ): bool => (int) ( $row[ $matches[1] ] ?? 0 ) === (int) $matches[2]
						&& (int) ( $row[ $matches[3] ] ?? 0 ) === (int) $matches[4]
				)
			);
		} elseif ( preg_match( '/WHERE\s+(\w+)\s*=\s*(\d+)\s+AND\s+(\w+)\s+IS\s+NULL/i', $query, $matches ) ) {
			$rows = array_values(
				array_filter(
					$rows,
					static fn( array $row ): bool => (int) ( $row[ $matches[1] ] ?? 0 ) === (int) $matches[2]
						&& null === ( $row[ $matches[3] ] ?? null )
				)
			);
		} elseif ( preg_match( '/WHERE\s+(\w+)\s*=\s*(\d+)/i', $query, $matches ) ) {
			$rows = array_values(
				array_filter(
					$rows,
					static fn( array $row ): bool => (int) ( $row[ $matches[1] ] ?? 0 ) === (int) $matches[2]
				)
			);
		}

		if ( str_contains( $query, 'deleted_at_utc IS NULL' ) ) {
			$rows = array_values(
				array_filter(
					$rows,
					static fn( array $row ): bool => null === ( $row['deleted_at_utc'] ?? null ) || '' === ( $row['deleted_at_utc'] ?? '' )
				)
			);
		}

		if ( preg_match( "/status IN \(([^)]+)\)/i", $query, $matches ) ) {
			preg_match_all( "/'([^']+)'/", $matches[1], $status_matches );
			$statuses = $status_matches[1] ?? [];
			if ( [] !== $statuses ) {
				$rows = array_values(
					array_filter(
						$rows,
						static fn( array $row ): bool => in_array( (string) ( $row['status'] ?? '' ), $statuses, true )
					)
				);
			}
		}

		if ( str_contains( $query, 'archived_at_utc IS NULL' ) ) {
			$rows = array_values(
				array_filter(
					$rows,
					static fn( array $row ): bool => null === ( $row['archived_at_utc'] ?? null ) || '' === ( $row['archived_at_utc'] ?? '' )
				)
			);
		}

		if ( preg_match( '/ORDER BY\s+guest_id\s+DESC/i', $query ) ) {
			usort(
				$rows,
				static fn( array $a, array $b ): int => (int) ( $b['guest_id'] ?? 0 ) <=> (int) ( $a['guest_id'] ?? 0 )
			);
		} elseif ( preg_match( '/ORDER BY\s+responded_at_utc\s+DESC,\s*guest_id\s+DESC/i', $query ) ) {
			usort(
				$rows,
				static function ( array $a, array $b ): int {
					$cmp = strcmp( (string) ( $b['responded_at_utc'] ?? '' ), (string) ( $a['responded_at_utc'] ?? '' ) );
					if ( 0 !== $cmp ) {
						return $cmp;
					}

					return (int) ( $b['guest_id'] ?? 0 ) <=> (int) ( $a['guest_id'] ?? 0 );
				}
			);
		} elseif ( preg_match( '/ORDER BY\s+sort_order\s+ASC,\s*wishlist_item_id\s+ASC/i', $query ) ) {
			usort(
				$rows,
				static function ( array $a, array $b ): int {
					$sort = (int) ( $a['sort_order'] ?? 0 ) <=> (int) ( $b['sort_order'] ?? 0 );
					if ( 0 !== $sort ) {
						return $sort;
					}

					return (int) ( $a['wishlist_item_id'] ?? 0 ) <=> (int) ( $b['wishlist_item_id'] ?? 0 );
				}
			);
		} elseif ( preg_match( '/ORDER BY\s+(\w+)\s+DESC/i', $query, $order_matches ) ) {
			$column = $order_matches[1];
			usort(
				$rows,
				static fn( array $a, array $b ): int => strcmp( (string) ( $b[ $column ] ?? '' ), (string) ( $a[ $column ] ?? '' ) )
			);
		}

		if ( preg_match( '/LIMIT\s+(\d+)\s+OFFSET\s+(\d+)/i', $query, $limit_matches ) ) {
			$limit  = (int) $limit_matches[1];
			$offset = (int) $limit_matches[2];
			$rows   = array_slice( $rows, $offset, $limit );
		}

		if ( preg_match( '/SELECT\s+COUNT\(\*\)/i', $query ) ) {
			return 'ARRAY_A' === $output ? array_fill( 0, count( $rows ), [ 'COUNT(*)' => count( $rows ) ] ) : [];
		}

		if ( preg_match( '/SELECT\s+project_id,/i', $query ) ) {
			$rows = array_map(
				static function ( array $row ): array {
					$allowed = [
						'project_id', 'user_id', 'order_id', 'order_item_id', 'product_id', 'event_title', 'status',
						'publication_status', 'event_start_utc', 'created_at_utc', 'updated_at_utc', 'last_error_code',
						'expires_at_utc', 'state_version',
					];

					return array_intersect_key( $row, array_flip( $allowed ) );
				},
				$rows
			);
		}

		if ( 'ARRAY_A' === $output ) {
			return $rows;
		}

		return array_map( static fn( array $row ): object => (object) $row, $rows );
	}

	public function prepare( string $query, ...$args ): string {
		if ( [] === $args ) {
			return $query;
		}

		$index = 0;

		return (string) preg_replace_callback(
			'/%[dfs]/',
			static function () use ( &$index, $args ): string {
				$value = $args[ $index ] ?? '';
				++$index;

				if ( is_int( $value ) ) {
					return (string) $value;
				}

				return "'" . str_replace( "'", "''", (string) $value ) . "'";
			},
			$query
		);
	}

	public function query( string $query ) {
		if ( str_starts_with( strtoupper( trim( $query ) ), 'START' ) || str_starts_with( strtoupper( trim( $query ) ), 'COMMIT' ) || str_starts_with( strtoupper( trim( $query ) ), 'ROLLBACK' ) ) {
			return true;
		}

		return false;
	}

	public function table_count( string $table ): int {
		return count( $this->tables[ $table ] ?? [] );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function assert_unique( string $table, array $data, ?int $skip_index = null ): bool {
		foreach ( $this->unique_keys[ $table ] ?? [] as $columns ) {
			foreach ( $this->tables[ $table ] ?? [] as $index => $row ) {
				if ( $skip_index === $index ) {
					continue;
				}

				$match = true;
				foreach ( $columns as $column ) {
					$value = $data[ $column ] ?? null;
					if ( null === $value || '' === $value ) {
						$match = false;
						break;
					}

					if ( (string) ( $row[ $column ] ?? '' ) !== (string) $value ) {
						$match = false;
						break;
					}
				}

				if ( $match ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $row
	 * @param array<string, mixed> $where
	 */
	private function row_matches( array $row, array $where ): bool {
		foreach ( $where as $column => $value ) {
			if ( (string) ( $row[ $column ] ?? '' ) !== (string) $value ) {
				return false;
			}
		}

		return true;
	}

	private function table_from_query( string $query ): string {
		if ( preg_match( '/FROM\s+(`?)([a-zA-Z0-9_]+)\1/i', $query, $matches ) ) {
			return $matches[2];
		}

		return '';
	}

	private function auto_increment_key( string $table ): ?string {
		return match ( $table ) {
			'wp_pks_oi_guests'                => 'guest_id',
			'wp_pks_oi_address_book'          => 'address_book_id',
			'wp_pks_oi_wishlist_items'        => 'wishlist_item_id',
			'wp_pks_oi_wishlist_reservations' => 'reservation_id',
			'wp_pks_oi_photos'                => 'photo_id',
			'wp_pks_oi_deliveries'            => 'delivery_id',
			'wp_pks_oi_events'                => 'event_id',
			default                           => null,
		};
	}
}
