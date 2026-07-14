<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Database\Repositories;

use PrikOgStreg\OnlineInvitations\Admin\ProjectAdminFilter;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

final class ProjectRepository extends AbstractRepository {

	public const COLUMNS = [
		'project_id',
		'storage_uuid',
		'user_id',
		'order_id',
		'order_item_id',
		'product_id',
		'template_id',
		'status',
		'publication_status',
		'locale',
		'timezone',
		'event_title',
		'event_start_utc',
		'event_end_utc',
		'venue_name',
		'venue_address_line1',
		'venue_address_line2',
		'venue_city',
		'venue_postcode',
		'venue_country',
		'practical_info',
		'organiser_display_name',
		'public_contact_email',
		'public_contact_phone',
		'rsvp_deadline_utc',
		'reminder_offset_days',
		'guest_photos_enabled',
		'internal_wishlist_enabled',
		'show_reserver_identity',
		'attendee_count_enabled',
		'comment_enabled',
		'dietary_notes_enabled',
		'expires_at_utc',
		'expiry_override_utc',
		'external_wishlist_url',
		'envelope_preset',
		'background_preset',
		'generic_token_hash',
		'generic_token_version',
		'builder_schema_version',
		'state_version',
		'published_version',
		'state_manifest_path',
		'published_manifest_path',
		'last_error_code',
		'created_at_utc',
		'updated_at_utc',
		'published_at_utc',
		'restricted_at_utc',
		'expired_at_utc',
		'deleted_at_utc',
	];

	private const FORMATS = [
		'project_id'               => '%d',
		'user_id'                  => '%d',
		'order_id'                 => '%d',
		'order_item_id'            => '%d',
		'product_id'               => '%d',
		'reminder_offset_days'     => '%d',
		'guest_photos_enabled'     => '%d',
		'internal_wishlist_enabled' => '%d',
		'show_reserver_identity'   => '%d',
		'attendee_count_enabled'   => '%d',
		'comment_enabled'          => '%d',
		'dietary_notes_enabled'    => '%d',
		'generic_token_version'    => '%d',
		'state_version'            => '%d',
		'published_version'        => '%d',
	];

	/**
	 * @param array<string, mixed> $data
	 */
	public function insert( array $data ): bool {
		$data = $this->filter_columns( $data, self::COLUMNS );
		$now  = UtcDateTime::now();
		$data['created_at_utc'] ??= $now;
		$data['updated_at_utc'] ??= $now;

		$columns = array_keys( $data );

		return false !== $this->wpdb->insert(
			$this->tables->projects(),
			$data,
			$this->formats_for( $columns, self::FORMATS )
		);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function update( int $project_id, array $data ): bool {
		$data = $this->filter_columns( $data, self::COLUMNS );
		unset( $data['project_id'] );
		$data['updated_at_utc'] = UtcDateTime::now();

		$columns = array_keys( $data );

		return false !== $this->wpdb->update(
			$this->tables->projects(),
			$data,
			[ 'project_id' => $project_id ],
			$this->formats_for( $columns, self::FORMATS ),
			[ '%d' ]
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_by_id( int $project_id ): ?array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->projects() . ' WHERE project_id = %d LIMIT 1',
			$project_id
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_by_order_item_id( int $order_item_id ): ?array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->projects() . ' WHERE order_item_id = %d LIMIT 1',
			$order_item_id
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_by_generic_token_hash( string $token_hash ): ?array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->projects() . ' WHERE generic_token_hash = %s LIMIT 1',
			$token_hash
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	public function delete_by_id( int $project_id ): bool {
		return false !== $this->wpdb->delete(
			$this->tables->projects(),
			[ 'project_id' => $project_id ],
			[ '%d' ]
		);
	}

	public function owned_by( int $project_id, int $user_id ): bool {
		$row = $this->find_by_id( $project_id );

		return is_array( $row ) && (int) ( $row['user_id'] ?? 0 ) === $user_id;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_owned_by_id( int $project_id, int $user_id ): ?array {
		$row = $this->find_by_id( $project_id );
		if ( ! is_array( $row ) || (int) ( $row['user_id'] ?? 0 ) !== $user_id ) {
			return null;
		}

		return $row;
	}

	public function count_for_user( int $user_id ): int {
		return $this->list_summary_for_user( $user_id, 1, PHP_INT_MAX )['total'];
	}

	/**
	 * Summary list for My Account — no builder payloads or HTML.
	 *
	 * @return array{
	 *     items:list<array<string,mixed>>,
	 *     total:int,
	 *     page:int,
	 *     per_page:int
	 * }
	 */
	public function list_summary_for_user( int $user_id, int $page = 1, int $per_page = 10 ): array {
		$page     = max( 1, $page );
		$per_page = max( 1, min( 50, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$sql = $this->wpdb->prepare(
			'SELECT project_id, event_title, status, publication_status, event_start_utc, updated_at_utc, order_id, product_id, expires_at_utc, last_error_code, state_version
			FROM ' . $this->tables->projects() . '
			WHERE user_id = %d AND deleted_at_utc IS NULL
			ORDER BY updated_at_utc DESC
			LIMIT %d OFFSET %d',
			$user_id,
			$per_page,
			$offset
		);

		$items = $this->wpdb->get_results( $sql, ARRAY_A );
		$total = $this->count_active_for_user( $user_id );

		return [
			'items'    => is_array( $items ) ? $items : [],
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		];
	}

	public function count_active_for_user( int $user_id ): int {
		$sql = $this->wpdb->prepare(
			'SELECT COUNT(*) FROM ' . $this->tables->projects() . ' WHERE user_id = %d AND deleted_at_utc IS NULL',
			$user_id
		);

		return (int) $this->wpdb->get_var( $sql );
	}

	/**
	 * Admin list of purchased invitation projects.
	 *
	 * @return array{
	 *     items:list<array<string,mixed>>,
	 *     total:int,
	 *     page:int,
	 *     per_page:int,
	 *     filter:string
	 * }
	 */
	public function list_admin_summaries( string $filter, int $page = 1, int $per_page = 20 ): array {
		$filter   = ProjectAdminFilter::sanitize( $filter );
		$page     = max( 1, $page );
		$per_page = max( 1, min( 100, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;
		$where    = $this->admin_filter_where_clause( $filter );

		$sql = 'SELECT project_id, user_id, order_id, order_item_id, product_id, event_title, status, publication_status, event_start_utc, created_at_utc, updated_at_utc, last_error_code
			FROM ' . $this->tables->projects() . '
			WHERE deleted_at_utc IS NULL' . $where . '
			ORDER BY updated_at_utc DESC
			LIMIT %d OFFSET %d';

		$items = $this->wpdb->get_results(
			$this->wpdb->prepare( $sql, $per_page, $offset ),
			ARRAY_A
		);

		return [
			'items'    => is_array( $items ) ? $items : [],
			'total'    => $this->count_admin_by_filter( $filter ),
			'page'     => $page,
			'per_page' => $per_page,
			'filter'   => $filter,
		];
	}

	public function count_admin_by_filter( string $filter ): int {
		$filter = ProjectAdminFilter::sanitize( $filter );
		$where  = $this->admin_filter_where_clause( $filter );
		$sql    = 'SELECT COUNT(*) FROM ' . $this->tables->projects() . ' WHERE deleted_at_utc IS NULL' . $where;

		return (int) $this->wpdb->get_var( $sql );
	}

	private function admin_filter_where_clause( string $filter ): string {
		return match ( ProjectAdminFilter::sanitize( $filter ) ) {
			ProjectAdminFilter::ACTIVE => $this->wpdb->prepare( ' AND status = %s', ProjectStatus::ACTIVE ),
			ProjectAdminFilter::DEACTIVATED => $this->wpdb->prepare(
				' AND status IN (' . implode( ', ', array_fill( 0, count( ProjectAdminFilter::deactivated_statuses() ), '%s' ) ) . ')',
				...ProjectAdminFilter::deactivated_statuses()
			),
			default => '',
		};
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_by_order_id( int $order_id ): array {
		if ( $order_id <= 0 ) {
			return [];
		}

		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->projects() . ' WHERE order_id = %d',
			$order_id
		);
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_active_past_expiry(): array {
		$now = gmdate( 'Y-m-d H:i:s' );
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->projects()
			. " WHERE status = %s AND expires_at_utc IS NOT NULL AND expires_at_utc <= %s AND deleted_at_utc IS NULL",
			ProjectStatus::ACTIVE,
			$now
		);
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_for_user( int $user_id ): array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->projects()
			. " WHERE user_id = %d AND deleted_at_utc IS NULL ORDER BY project_id ASC",
			$user_id
		);

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * @return list<string>
	 */
	public function list_storage_uuids(): array {
		$sql  = 'SELECT storage_uuid FROM ' . $this->tables->projects() . " WHERE storage_uuid IS NOT NULL AND storage_uuid <> ''";
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return [];
		}

		$uuids = [];
		foreach ( $rows as $row ) {
			$uuid = (string) ( $row['storage_uuid'] ?? '' );
			if ( '' !== $uuid ) {
				$uuids[] = $uuid;
			}
		}

		return $uuids;
	}
}

