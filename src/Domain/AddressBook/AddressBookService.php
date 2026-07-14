<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\AddressBook;

use PrikOgStreg\OnlineInvitations\Database\Repositories\AddressBookRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\EventRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestService;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

/**
 * Private per-user address book with snapshot semantics for project guests.
 */
final class AddressBookService {

	public const PER_PAGE = 20;

	public function __construct(
		private AddressBookRepository $address_book,
		private GuestRepository $guests,
		private GuestService $guest_service,
		private EventRepository $events
	) {}

	/**
	 * @param array<string, mixed> $input
	 * @return array{success:bool,address_book_id?:int,error?:string,warnings?:list<string>}
	 */
	public function create( int $user_id, array $input ): array {
		$normalized = $this->normalize_input( $input );
		if ( isset( $normalized['error'] ) ) {
			return [ 'success' => false, 'error' => (string) $normalized['error'] ];
		}

		$id = $this->address_book->insert( array_merge( $normalized, [ 'user_id' => $user_id ] ) );
		if ( $id <= 0 ) {
			return [ 'success' => false, 'error' => 'create_failed' ];
		}

		return [
			'success'         => true,
			'address_book_id' => $id,
			'warnings'        => $this->duplicate_email_warnings( $user_id, (string) ( $normalized['email'] ?? '' ) ),
		];
	}

	/**
	 * @param array<string, mixed> $contact
	 * @param array<string, mixed> $input
	 * @return array{success:bool,error?:string,warnings?:list<string>}
	 */
	public function update( int $user_id, array $contact, array $input ): array {
		if ( (int) ( $contact['user_id'] ?? 0 ) !== $user_id ) {
			return [ 'success' => false, 'error' => 'forbidden' ];
		}

		$normalized = $this->normalize_input( $input );
		if ( isset( $normalized['error'] ) ) {
			return [ 'success' => false, 'error' => (string) $normalized['error'] ];
		}

		$ok = $this->address_book->update( (int) $contact['address_book_id'], $normalized );
		if ( ! $ok ) {
			return [ 'success' => false, 'error' => 'update_failed' ];
		}

		return [
			'success'  => true,
			'warnings' => $this->duplicate_email_warnings(
				$user_id,
				(string) ( $normalized['email'] ?? '' ),
				(int) $contact['address_book_id']
			),
		];
	}

	public function archive( int $user_id, int $address_book_id ): bool {
		$contact = $this->address_book->find_by_id_for_user( $address_book_id, $user_id );
		if ( ! is_array( $contact ) ) {
			return false;
		}

		return $this->address_book->update(
			$address_book_id,
			[ 'archived_at_utc' => UtcDateTime::now() ]
		);
	}

	public function delete( int $user_id, int $address_book_id ): bool {
		$contact = $this->address_book->find_by_id_for_user( $address_book_id, $user_id );
		if ( ! is_array( $contact ) ) {
			return false;
		}

		return $this->address_book->delete_for_user( $address_book_id, $user_id );
	}

	/**
	 * @param array<string, mixed> $project
	 * @param list<int>            $address_book_ids
	 * @return array{added:int,skipped:int}
	 */
	public function add_contacts_to_project( array $project, int $user_id, array $address_book_ids ): array {
		$added    = 0;
		$skipped  = 0;

		foreach ( $address_book_ids as $address_book_id ) {
			$contact = $this->address_book->find_by_id_for_user( (int) $address_book_id, $user_id );
			if ( ! is_array( $contact ) || null !== ( $contact['archived_at_utc'] ?? null ) ) {
				++$skipped;
				continue;
			}

			$result = $this->guest_service->create(
				$project,
				[
					'display_name'    => (string) ( $contact['display_name'] ?? '' ),
					'email'           => (string) ( $contact['email'] ?? '' ),
					'phone'           => (string) ( $contact['phone'] ?? '' ),
					'address_book_id' => (int) $contact['address_book_id'],
				]
			);

			if ( empty( $result['success'] ) ) {
				++$skipped;
				continue;
			}

			++$added;
		}

		return [ 'added' => $added, 'skipped' => $skipped ];
	}

	/**
	 * @param array<string, mixed> $guest
	 */
	public function save_guest_snapshot( int $user_id, array $guest ): array {
		$name = trim( (string) ( $guest['display_name'] ?? '' ) );
		if ( '' === $name ) {
			return [ 'success' => false, 'error' => 'missing_display_name' ];
		}

		$email = (string) ( $guest['email'] ?? '' );
		$hash  = self::normalized_email_hash( $email );
		if ( '' !== $hash ) {
			$existing = $this->address_book->find_by_normalized_email_hash( $user_id, $hash );
			if ( is_array( $existing ) ) {
				return [
					'success'         => true,
					'address_book_id' => (int) $existing['address_book_id'],
					'warnings'        => [ __( 'A matching e-mail already exists in your address book.', 'prikogstreg-online-invitations' ) ],
				];
			}
		}

		return $this->create(
			$user_id,
			[
				'display_name' => $name,
				'email'        => $email,
				'phone'        => (string) ( $guest['phone'] ?? '' ),
			]
		);
	}

	/**
	 * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int}
	 */
	public function list_for_user( int $user_id, int $page, string $search = '' ): array {
		return $this->address_book->list_for_user( $user_id, $page, self::PER_PAGE, $search );
	}

	public function log_support_access( int $project_id, int $actor_user_id, int $owner_user_id ): void {
		$this->events->insert(
			[
				'project_id'    => $project_id,
				'actor_type'    => 'support',
				'event_type'    => 'address_book_support_view',
				'metadata_json' => wp_json_encode(
					[
						'actor_user_id' => $actor_user_id,
						'owner_user_id' => $owner_user_id,
					]
				) ?: '{}',
			]
		);
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	private function normalize_input( array $input ): array {
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

		return [
			'display_name'          => sanitize_text_field( $name ),
			'email'                 => $email,
			'phone'                 => $this->nullable_field( $input, 'phone' ),
			'notes'                 => $this->nullable_textarea( $input, 'notes' ),
			'normalized_email_hash' => self::normalized_email_hash( (string) ( $email ?? '' ) ) ?: null,
		];
	}

	/**
	 * @param array<string, mixed> $input
	 */
	private function nullable_field( array $input, string $key ): ?string {
		$value = trim( (string) ( $input[ $key ] ?? '' ) );

		return '' !== $value ? sanitize_text_field( $value ) : null;
	}

	/**
	 * @param array<string, mixed> $input
	 */
	private function nullable_textarea( array $input, string $key ): ?string {
		$value = trim( (string) ( $input[ $key ] ?? '' ) );

		return '' !== $value ? sanitize_textarea_field( $value ) : null;
	}

	public static function normalized_email_hash( string $email ): string {
		$email = strtolower( trim( $email ) );
		if ( '' === $email ) {
			return '';
		}

		return hash( 'sha256', $email );
	}

	/**
	 * @return list<string>
	 */
	private function duplicate_email_warnings( int $user_id, string $email, ?int $exclude_id = null ): array {
		$hash = self::normalized_email_hash( $email );
		if ( '' === $hash ) {
			return [];
		}

		$existing = $this->address_book->find_by_normalized_email_hash( $user_id, $hash );
		if ( ! is_array( $existing ) ) {
			return [];
		}

		if ( null !== $exclude_id && (int) $existing['address_book_id'] === $exclude_id ) {
			return [];
		}

		return [ __( 'Another address-book contact already uses this e-mail.', 'prikogstreg-online-invitations' ) ];
	}
}
