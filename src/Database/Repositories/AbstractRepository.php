<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Database\Repositories;

use PrikOgStreg\OnlineInvitations\Database\TableNames;

/**
 * Shared repository helpers with explicit column allowlists.
 */
abstract class AbstractRepository {

	/**
	 * @param object $wpdb WordPress database object.
	 */
	public function __construct(
		protected object $wpdb,
		protected TableNames $tables
	) {}

	/**
	 * @param array<string, mixed> $data
	 * @param list<string>           $allowed
	 * @return array<string, mixed>
	 */
	protected function filter_columns( array $data, array $allowed ): array {
		return array_intersect_key( $data, array_flip( $allowed ) );
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array{0: list<string>, 1: list<mixed>}
	 */
	protected function build_insert_parts( array $data ): array {
		$columns = array_keys( $data );
		$holders = array_fill( 0, count( $columns ), '%s' );

		return [ $columns, $holders ];
	}

	/**
	 * @param list<string> $columns
	 * @return list<string>
	 */
	protected function formats_for( array $columns, array $format_map ): array {
		return array_map(
			static fn( string $column ): string => $format_map[ $column ] ?? '%s',
			$columns
		);
	}
}
