<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Database\Repositories;

use PrikOgStreg\OnlineInvitations\Domain\Guest\InvitationStatus;
use PrikOgStreg\OnlineInvitations\Domain\Guest\RsvpStatus;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

final class GuestRepository extends AbstractRepository {

	public const COLUMNS = [
		'guest_id',
		'project_id',
		'address_book_id',
		'display_name',
		'email',
		'phone',
		'party_label',
		'token_hash',
		'token_version',
		'rsvp_status',
		'attendee_count',
		'rsvp_comment',
		'dietary_notes',
		'invitation_status',
		'is_generic_response',
		'first_sent_at_utc',
		'last_sent_at_utc',
		'first_opened_at_utc',
		'last_opened_at_utc',
		'open_count',
		'responded_at_utc',
		'archived_at_utc',
		'created_at_utc',
		'updated_at_utc',
	];

	private const FORMATS = [
		'guest_id'            => '%d',
		'project_id'          => '%d',
		'address_book_id'   => '%d',
		'token_version'     => '%d',
		'attendee_count'    => '%d',
		'is_generic_response' => '%d',
		'open_count'          => '%d',
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
			$this->tables->guests(),
			$data,
			$this->formats_for( $columns, self::FORMATS )
		);

		return false === $result ? 0 : (int) $this->wpdb->insert_id;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function update( int $guest_id, array $data ): bool {
		$data = $this->filter_columns( $data, self::COLUMNS );
		unset( $data['guest_id'] );
		$data['updated_at_utc'] = UtcDateTime::now();

		$columns = array_keys( $data );

		return false !== $this->wpdb->update(
			$this->tables->guests(),
			$data,
			[ 'guest_id' => $guest_id ],
			$this->formats_for( $columns, self::FORMATS ),
			[ '%d' ]
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_by_id( int $guest_id ): ?array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->guests() . ' WHERE guest_id = %d LIMIT 1',
			$guest_id
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_by_id_for_project( int $guest_id, int $project_id ): ?array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->guests() . ' WHERE guest_id = %d AND project_id = %d LIMIT 1',
			$guest_id,
			$project_id
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int}
	 */
	public function list_for_project_filtered( int $project_id, int $page, int $per_page, ?string $rsvp_status = null ): array {
		$page     = max( 1, $page );
		$per_page = max( 1, min( 100, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;
		$total    = $this->count_for_project_filtered( $project_id, $rsvp_status );

		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->guests() . ' WHERE project_id = %d AND archived_at_utc IS NULL ORDER BY responded_at_utc DESC, guest_id DESC LIMIT %d OFFSET %d',
			$project_id,
			$per_page,
			$offset
		);

		$items = $this->wpdb->get_results( $sql, ARRAY_A );
		$items = is_array( $items ) ? $items : [];

		if ( null !== $rsvp_status && '' !== $rsvp_status ) {
			$items = array_values(
				array_filter(
					$items,
					static fn( array $row ): bool => $rsvp_status === (string) ( $row['rsvp_status'] ?? '' )
				)
			);
		}

		return [
			'items'    => $items,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		];
	}

	public function count_for_project_filtered( int $project_id, ?string $rsvp_status = null ): int {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->guests() . ' WHERE project_id = %d AND archived_at_utc IS NULL',
			$project_id
		);

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return 0;
		}

		if ( null === $rsvp_status || '' === $rsvp_status ) {
			return count( $rows );
		}

		$count = 0;
		foreach ( $rows as $row ) {
			if ( $rsvp_status === (string) ( $row['rsvp_status'] ?? '' ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int}
	 */
	public function list_for_project( int $project_id, int $page, int $per_page, bool $include_archived = false ): array {
		$page     = max( 1, $page );
		$per_page = max( 1, min( 100, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;
		$total    = $this->count_for_project( $project_id, ! $include_archived );

		$archived_clause = $include_archived ? '' : ' AND archived_at_utc IS NULL';

		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->guests() . ' WHERE project_id = %d' . $archived_clause . ' ORDER BY guest_id DESC LIMIT %d OFFSET %d',
			$project_id,
			$per_page,
			$offset
		);

		$items = $this->wpdb->get_results( $sql, ARRAY_A );

		return [
			'items'    => is_array( $items ) ? $items : [],
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		];
	}

	public function count_for_project( int $project_id, bool $active_only = true ): int {
		$archived_clause = $active_only ? ' AND archived_at_utc IS NULL' : '';
		$sql             = $this->wpdb->prepare(
			'SELECT COUNT(*) FROM ' . $this->tables->guests() . ' WHERE project_id = %d' . $archived_clause,
			$project_id
		);

		return (int) $this->wpdb->get_var( $sql );
	}

	public function count_duplicate_email( int $project_id, string $email, ?int $exclude_guest_id = null ): int {
		$email = trim( $email );
		if ( '' === $email ) {
			return 0;
		}

		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->guests() . ' WHERE project_id = %d AND email = %s AND archived_at_utc IS NULL',
			$project_id,
			$email
		);

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $rows as $row ) {
			if ( null !== $exclude_guest_id && (int) ( $row['guest_id'] ?? 0 ) === $exclude_guest_id ) {
				continue;
			}
			++$count;
		}

		return $count;
	}

	/**
	 * @return array<string, int>
	 */
	public function status_summary( int $project_id ): array {
		$sql  = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->guests() . ' WHERE project_id = %d AND archived_at_utc IS NULL',
			$project_id
		);
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		$summary = [
			'total'          => 0,
			'pending_rsvp'   => 0,
			'attending'      => 0,
			'declined'       => 0,
			'opened'         => 0,
			'not_sent'       => 0,
		];

		if ( ! is_array( $rows ) ) {
			return $summary;
		}

		foreach ( $rows as $row ) {
			++$summary['total'];
			$rsvp = (string) ( $row['rsvp_status'] ?? RsvpStatus::PENDING );
			if ( RsvpStatus::ATTENDING === $rsvp ) {
				++$summary['attending'];
			} elseif ( RsvpStatus::DECLINED === $rsvp ) {
				++$summary['declined'];
			} else {
				++$summary['pending_rsvp'];
			}

			if ( '' !== (string) ( $row['first_opened_at_utc'] ?? '' ) ) {
				++$summary['opened'];
			}

			if ( InvitationStatus::NOT_SENT === (string) ( $row['invitation_status'] ?? '' ) ) {
				++$summary['not_sent'];
			}
		}

		return $summary;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function export_rows_for_project( int $project_id ): array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->guests() . ' WHERE project_id = %d ORDER BY guest_id ASC',
			$project_id
		);

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_by_token_hash( string $token_hash ): ?array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->guests() . ' WHERE token_hash = %s LIMIT 1',
			$token_hash
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_by_project( int $project_id ): array {
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->guests() . ' WHERE project_id = %d ORDER BY guest_id ASC',
			$project_id
		);

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}

	public function delete_by_project( int $project_id ): bool {
		return false !== $this->wpdb->delete(
			$this->tables->guests(),
			[ 'project_id' => $project_id ],
			[ '%d' ]
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_for_user_projects( int $user_id ): array {
		$sql = $this->wpdb->prepare(
			'SELECT g.* FROM ' . $this->tables->guests() . ' g
			INNER JOIN ' . $this->tables->projects() . ' p ON p.project_id = g.project_id
			WHERE p.user_id = %d
			ORDER BY g.guest_id ASC',
			$user_id
		);

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_by_email( string $email ): array {
		$email = strtolower( trim( $email ) );
		if ( '' === $email ) {
			return [];
		}

		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->tables->guests() . ' WHERE email = %s ORDER BY guest_id ASC',
			$email
		);

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}
}
