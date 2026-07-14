<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Privacy;

use PrikOgStreg\OnlineInvitations\Database\Repositories\AddressBookRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectHardDeleteService;

/**
 * Erases personal data via WordPress privacy tools and preserves commerce records.
 */
final class PersonalDataEraser {

	public function __construct(
		private ProjectRepository $projects,
		private GuestRepository $guests,
		private AddressBookRepository $address_book,
		private GuestAnonymizer $guest_anonymizer,
		private ProjectHardDeleteService $hard_delete
	) {}

	/**
	 * @return array{items_removed:bool,items_retained:bool,messages:list<string>,done:bool}
	 */
	public function erase_for_email( string $email, int $page = 1 ): array {
		$email = strtolower( trim( $email ) );
		if ( '' === $email ) {
			return $this->result( false, false, [ __( 'No e-mail address provided.', 'prikogstreg-online-invitations' ) ], true );
		}

		$messages      = [];
		$removed       = false;
		$retained      = false;
		$user_id       = $this->resolve_user_id( $email );
		$guest_matches = $this->guests->list_by_email( $email );

		if ( $user_id > 0 ) {
			$deleted_contacts = $this->address_book->delete_all_for_user( $user_id );
			if ( $deleted_contacts > 0 ) {
				$removed  = true;
				$messages[] = sprintf(
					/* translators: %d: number of contacts */
					__( 'Removed %d address book contacts.', 'prikogstreg-online-invitations' ),
					$deleted_contacts
				);
			}

			foreach ( $this->projects->list_for_user( $user_id ) as $project ) {
				$order_id = (int) ( $project['order_id'] ?? 0 );
				if ( $order_id > 0 ) {
					$retained = true;
				}

				$result = $this->hard_delete->delete( $project, 'privacy_eraser', 'privacy' );
				if ( $result->success || $result->done ) {
					$removed = true;
					$messages[] = sprintf(
						/* translators: %d: project ID */
						__( 'Deleted invitation project #%d.', 'prikogstreg-online-invitations' ),
						(int) ( $project['project_id'] ?? 0 )
					);
				}
				foreach ( $result->errors as $error ) {
					$messages[] = sprintf(
						/* translators: 1: project ID, 2: error code */
						__( 'Project #%1$d partial cleanup: %2$s', 'prikogstreg-online-invitations' ),
						(int) ( $project['project_id'] ?? 0 ),
						$error
					);
				}
			}

			if ( $retained ) {
				$messages[] = __( 'WooCommerce order records may be retained for legal and accounting purposes.', 'prikogstreg-online-invitations' );
			}
		}

		foreach ( $guest_matches as $guest ) {
			$project_id = (int) ( $guest['project_id'] ?? 0 );
			$project    = $this->projects->find_by_id( $project_id );
			if ( ! is_array( $project ) ) {
				continue;
			}

			if ( $user_id > 0 && (int) ( $project['user_id'] ?? 0 ) === $user_id ) {
				continue;
			}

			if ( $this->guest_anonymizer->is_anonymized( $guest ) ) {
				continue;
			}

			if ( $this->guest_anonymizer->anonymize( $guest ) ) {
				$removed    = true;
				$messages[] = sprintf(
					/* translators: %d: guest ID */
					__( 'Anonymized guest record #%d.', 'prikogstreg-online-invitations' ),
					(int) ( $guest['guest_id'] ?? 0 )
				);
			}
		}

		if ( ! $removed && ! $retained ) {
			$messages[] = __( 'No matching invitation data found for this e-mail.', 'prikogstreg-online-invitations' );
		}

		return $this->result( $removed, $retained, $messages, true );
	}

	/**
	 * @param list<string> $messages
	 * @return array{items_removed:bool,items_retained:bool,messages:list<string>,done:bool}
	 */
	private function result( bool $removed, bool $retained, array $messages, bool $done ): array {
		return [
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => $messages,
			'done'           => $done,
		];
	}

	private function resolve_user_id( string $email ): int {
		if ( ! function_exists( 'get_user_by' ) ) {
			return 0;
		}

		$user = get_user_by( 'email', $email );
		if ( ! is_object( $user ) || ! isset( $user->ID ) ) {
			return 0;
		}

		return (int) $user->ID;
	}
}
