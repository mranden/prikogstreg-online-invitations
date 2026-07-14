<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Database\Repositories;

use PrikOgStreg\OnlineInvitations\Admin\Invitations\InvitationAdminQuery;
use PrikOgStreg\OnlineInvitations\Admin\ProjectAdminFilter;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoModerationStatus;
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
		'photo_share_token_hash',
		'photo_share_token_version',
		'photo_access_code_hash',
		'photo_access_code_version',
		'photo_auto_approve_enabled',
		'photo_gallery_public_enabled',
		'photo_upload_closes_at_utc',
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
		'envelope_image_id',
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
		'envelope_image_id'        => '%d',
		'reminder_offset_days'     => '%d',
		'guest_photos_enabled'          => '%d',
		'photo_share_token_version'     => '%d',
		'photo_access_code_version'     => '%d',
		'photo_auto_approve_enabled'      => '%d',
		'photo_gallery_public_enabled'    => '%d',
		'internal_wishlist_enabled'     => '%d',
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

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_by_photo_share_token_hash( string $token_hash ): ?array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->projects() . ' WHERE photo_share_token_hash = %s LIMIT 1',
			$token_hash
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_with_photos_enabled_missing_share_token(): array {
		$sql = 'SELECT * FROM ' . $this->tables->projects()
			. ' WHERE guest_photos_enabled = 1 AND (photo_share_token_hash IS NULL OR photo_share_token_hash = \'\') AND deleted_at_utc IS NULL';

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : [];
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
		return $this->list_admin_query(
			[
				'filter'   => $filter,
				'page'     => $page,
				'per_page' => $per_page,
			]
		);
	}

	/**
	 * Admin list with search, filters and sorting.
	 *
	 * @param array<string, mixed> $args
	 * @return array{
	 *     items:list<array<string,mixed>>,
	 *     total:int,
	 *     page:int,
	 *     per_page:int,
	 *     filter:string
	 * }
	 */
	public function list_admin_query( array $args ): array {
		$filter   = ProjectAdminFilter::sanitize( (string) ( $args['filter'] ?? ProjectAdminFilter::ALL ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where_parts = [ 'deleted_at_utc IS NULL' ];
		$filter_part = $this->admin_filter_condition( $filter );
		if ( null !== $filter_part ) {
			$where_parts[] = $filter_part;
		}
		$where_parts = array_merge( $where_parts, $this->admin_extra_where_clauses( $args ) );
		$where_sql     = ' WHERE ' . implode( ' AND ', $where_parts );

		$orderby = $this->admin_sanitize_orderby( (string) ( $args['orderby'] ?? 'updated_at_utc' ) );
		$order   = 'ASC' === strtoupper( (string) ( $args['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC';

		$sql = 'SELECT project_id, user_id, order_id, order_item_id, product_id, template_id, event_title, status, publication_status, event_start_utc, created_at_utc, updated_at_utc, last_error_code
			FROM ' . $this->tables->projects() . $where_sql . '
			ORDER BY ' . $orderby . ' ' . $order . '
			LIMIT %d OFFSET %d';

		$items = $this->wpdb->get_results(
			$this->wpdb->prepare( $sql, $per_page, $offset ),
			ARRAY_A
		);

		return [
			'items'    => is_array( $items ) ? $items : [],
			'total'    => $this->count_admin_query( array_merge( $args, [ 'filter' => $filter ] ) ),
			'page'     => $page,
			'per_page' => $per_page,
			'filter'   => $filter,
		];
	}

	public function count_admin_by_filter( string $filter ): int {
		return $this->count_admin_query( [ 'filter' => $filter ] );
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public function count_admin_query( array $args ): int {
		$filter = ProjectAdminFilter::sanitize( (string) ( $args['filter'] ?? ProjectAdminFilter::ALL ) );

		$where_parts = [ 'deleted_at_utc IS NULL' ];
		$filter_part = $this->admin_filter_condition( $filter );
		if ( null !== $filter_part ) {
			$where_parts[] = $filter_part;
		}
		$where_parts = array_merge( $where_parts, $this->admin_extra_where_clauses( $args ) );
		$where_sql     = ' WHERE ' . implode( ' AND ', $where_parts );

		$sql = 'SELECT COUNT(*) FROM ' . $this->tables->projects() . $where_sql;

		return (int) $this->wpdb->get_var( $sql );
	}

	/**
	 * @param array<string, mixed> $args
	 * @return list<string>
	 */
	private function admin_extra_where_clauses( array $args ): array {
		$clauses = [];

		$search = trim( (string) ( $args['search'] ?? '' ) );
		if ( '' !== $search ) {
			$like = '%' . $this->wpdb->esc_like( $search ) . '%';
			$search_parts = [ $this->wpdb->prepare( 'event_title LIKE %s', $like ) ];

			if ( ctype_digit( $search ) ) {
				$id = (int) $search;
				$search_parts[] = $this->wpdb->prepare( 'project_id = %d', $id );
				$search_parts[] = $this->wpdb->prepare( 'order_id = %d', $id );
			}

			$users = $this->wpdb->users;
			$search_parts[] = $this->wpdb->prepare(
				"user_id IN (SELECT ID FROM {$users} WHERE display_name LIKE %s OR user_email LIKE %s)",
				$like,
				$like
			);

			$clauses[] = '(' . implode( ' OR ', $search_parts ) . ')';
		}

		$publication = sanitize_key( (string) ( $args['publication_status'] ?? '' ) );
		if ( '' !== $publication ) {
			$clauses[] = $this->wpdb->prepare( 'publication_status = %s', $publication );
		}

		$event_date = sanitize_key( (string) ( $args['event_date'] ?? '' ) );
		$now        = gmdate( 'Y-m-d H:i:s' );
		if ( InvitationAdminQuery::EVENT_UPCOMING === $event_date ) {
			$clauses[] = $this->wpdb->prepare( 'event_start_utc IS NOT NULL AND event_start_utc > %s', $now );
		} elseif ( InvitationAdminQuery::EVENT_PAST === $event_date ) {
			$clauses[] = $this->wpdb->prepare( 'event_start_utc IS NOT NULL AND event_start_utc <= %s', $now );
		} elseif ( InvitationAdminQuery::EVENT_NONE === $event_date ) {
			$clauses[] = 'event_start_utc IS NULL';
		}

		$product_id = (int) ( $args['product_id'] ?? 0 );
		if ( $product_id > 0 ) {
			$clauses[] = $this->wpdb->prepare( 'product_id = %d', $product_id );
		}

		if ( ! empty( $args['has_pending_photos'] ) ) {
			$photos = $this->tables->photos();
			$clauses[] = "project_id IN (SELECT project_id FROM {$photos} WHERE deleted_at_utc IS NULL AND moderation_status = '" . PhotoModerationStatus::PENDING . "')";
		}

		$order_status = sanitize_key( (string) ( $args['order_status'] ?? '' ) );
		if ( '' !== $order_status && function_exists( 'wc_get_order_statuses' ) ) {
			$normalized = str_starts_with( $order_status, 'wc-' ) ? $order_status : 'wc-' . $order_status;
			$posts      = $this->wpdb->posts;
			$clauses[]  = $this->wpdb->prepare(
				"order_id IN (SELECT ID FROM {$posts} WHERE post_type = 'shop_order' AND post_status = %s)",
				$normalized
			);
		}

		return $clauses;
	}

	private function admin_sanitize_orderby( string $orderby ): string {
		$allowed = [
			'updated_at_utc',
			'created_at_utc',
			'event_start_utc',
			'publication_status',
			'status',
			'order_id',
			'project_id',
		];
		$orderby = sanitize_key( $orderby );

		return in_array( $orderby, $allowed, true ) ? $orderby : 'updated_at_utc';
	}

	private function admin_filter_condition( string $filter ): ?string {
		return match ( ProjectAdminFilter::sanitize( $filter ) ) {
			ProjectAdminFilter::ACTIVE => $this->wpdb->prepare( 'status = %s', ProjectStatus::ACTIVE ),
			ProjectAdminFilter::DEACTIVATED => $this->wpdb->prepare(
				'status IN (' . implode( ', ', array_fill( 0, count( ProjectAdminFilter::deactivated_statuses() ), '%s' ) ) . ')',
				...ProjectAdminFilter::deactivated_statuses()
			),
			default => null,
		};
	}

	private function admin_filter_where_clause( string $filter ): string {
		$condition = $this->admin_filter_condition( $filter );

		return null !== $condition ? ' AND ' . $condition : '';
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

