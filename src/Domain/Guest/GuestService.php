<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Guest;

use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;

/**
 * Guest CRUD with unlimited capacity and independent tokens.
 */
final class GuestService {

	public const PER_PAGE = 20;

	public function __construct(
		private GuestRepository $guests,
		private GuestTokenService $tokens
	) {}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $input
	 * @return array{success:bool,guest_id?:int,error?:string,warnings?:list<string>,invitation_url?:string}
	 */
	public function create( array $project, array $input ): array {
		$normalized = $this->normalize_input( $input, true );
		if ( isset( $normalized['error'] ) ) {
			return [ 'success' => false, 'error' => (string) $normalized['error'] ];
		}

		$pair = InvitationToken::generate();
		$guest_id = $this->guests->insert(
			array_merge(
				$normalized,
				[
					'project_id'        => (int) $project['project_id'],
					'token_hash'        => $pair['hash'],
					'token_version'     => 1,
					'rsvp_status'       => RsvpStatus::PENDING,
					'invitation_status' => InvitationStatus::NOT_SENT,
				]
			)
		);

		if ( $guest_id <= 0 ) {
			return [ 'success' => false, 'error' => 'create_failed' ];
		}

		$url = InvitationToken::public_url( $pair['raw'] );
		GuestSendTokenStore::remember( $guest_id, $pair['raw'] );

		return [
			'success'         => true,
			'guest_id'        => $guest_id,
			'warnings'        => $this->duplicate_email_warnings( (int) $project['project_id'], (string) ( $normalized['email'] ?? '' ) ),
			'invitation_url'  => $url,
		];
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $guest
	 * @param array<string, mixed> $input
	 * @return array{success:bool,error?:string,warnings?:list<string>}
	 */
	public function update( array $project, array $guest, array $input ): array {
		if ( (int) ( $guest['project_id'] ?? 0 ) !== (int) ( $project['project_id'] ?? 0 ) ) {
			return [ 'success' => false, 'error' => 'forbidden' ];
		}

		$normalized = $this->normalize_input( $input, false );
		if ( isset( $normalized['error'] ) ) {
			return [ 'success' => false, 'error' => (string) $normalized['error'] ];
		}

		$ok = $this->guests->update( (int) $guest['guest_id'], $normalized );
		if ( ! $ok ) {
			return [ 'success' => false, 'error' => 'update_failed' ];
		}

		return [
			'success'  => true,
			'warnings' => $this->duplicate_email_warnings(
				(int) $project['project_id'],
				(string) ( $normalized['email'] ?? '' ),
				(int) $guest['guest_id']
			),
		];
	}

	/**
	 * @param array<string, mixed> $project
	 * @param list<int>            $guest_ids
	 */
	public function archive_many( array $project, array $guest_ids ): int {
		$archived = 0;
		foreach ( $guest_ids as $guest_id ) {
			$guest = $this->guests->find_by_id_for_project( (int) $guest_id, (int) $project['project_id'] );
			if ( ! is_array( $guest ) ) {
				continue;
			}
			$this->tokens->revoke( $guest );
			++$archived;
		}

		return $archived;
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $guest
	 * @return array{success:bool,invitation_url?:string,error?:string}
	 */
	public function restore( array $project, array $guest ): array {
		if ( (int) ( $guest['project_id'] ?? 0 ) !== (int) ( $project['project_id'] ?? 0 ) ) {
			return [ 'success' => false, 'error' => 'forbidden' ];
		}

		$result = $this->tokens->restore_access( $guest );

		return [
			'success'        => true,
			'invitation_url' => $result['url'],
		];
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $guest
	 * @return array{success:bool,invitation_url?:string,error?:string}
	 */
	public function regenerate_link( array $project, array $guest ): array {
		if ( (int) ( $guest['project_id'] ?? 0 ) !== (int) ( $project['project_id'] ?? 0 ) ) {
			return [ 'success' => false, 'error' => 'forbidden' ];
		}

		if ( null !== ( $guest['archived_at_utc'] ?? null ) && '' !== (string) $guest['archived_at_utc'] ) {
			return [ 'success' => false, 'error' => 'guest_archived' ];
		}

		$result = $this->tokens->rotate( $guest );

		return [
			'success'        => true,
			'invitation_url' => $result['url'],
		];
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,summary:array<string,int>}
	 */
	public function list_for_project( array $project, int $page, bool $include_archived = false ): array {
		$project_id = (int) $project['project_id'];
		$result     = $this->guests->list_for_project( $project_id, $page, self::PER_PAGE, $include_archived );

		return [
			'items'    => $result['items'],
			'total'    => $result['total'],
			'page'     => $result['page'],
			'per_page' => $result['per_page'],
			'summary'  => $this->guests->status_summary( $project_id ),
		];
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	private function normalize_input( array $input, bool $is_create ): array {
		$name = trim( (string) ( $input['display_name'] ?? '' ) );
		if ( '' === $name ) {
			return [ 'error' => 'missing_display_name' ];
		}

		$email = trim( (string) ( $input['email'] ?? '' ) );
		if ( '' !== $email ) {
			$email = sanitize_email( $email );
			if ( '' === $email ) {
				return [ 'error' => 'invalid_email' ];
			}
		} else {
			$email = null;
		}

		$data = [
			'display_name' => sanitize_text_field( $name ),
			'email'        => $email,
			'phone'        => $this->nullable_field( $input, 'phone' ),
			'party_label'  => $this->nullable_field( $input, 'party_label' ),
		];

		if ( array_key_exists( 'attendee_count', $input ) && '' !== (string) $input['attendee_count'] ) {
			$data['attendee_count'] = max( 1, (int) $input['attendee_count'] );
		} elseif ( $is_create ) {
			$data['attendee_count'] = null;
		}

		if ( array_key_exists( 'address_book_id', $input ) ) {
			$data['address_book_id'] = (int) $input['address_book_id'] > 0 ? (int) $input['address_book_id'] : null;
		}

		return $data;
	}

	/**
	 * @param array<string, mixed> $input
	 */
	private function nullable_field( array $input, string $key ): ?string {
		$value = trim( (string) ( $input[ $key ] ?? '' ) );

		return '' !== $value ? sanitize_text_field( $value ) : null;
	}

	/**
	 * @return list<string>
	 */
	private function duplicate_email_warnings( int $project_id, string $email, ?int $exclude_guest_id = null ): array {
		if ( '' === $email ) {
			return [];
		}

		$count = $this->guests->count_duplicate_email( $project_id, $email, $exclude_guest_id );

		return $count > 0
			? [ __( 'Another guest on this project already uses this e-mail address.', 'prikogstreg-online-invitations' ) ]
			: [];
	}
}
