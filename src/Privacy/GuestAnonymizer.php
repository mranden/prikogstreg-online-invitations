<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Privacy;

use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestTokenService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoService;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

/**
 * Anonymizes guest personal data while retaining aggregate RSVP counts.
 */
final class GuestAnonymizer {

	public function __construct(
		private GuestRepository $guests,
		private GuestTokenService $tokens,
		private PhotoService $photos
	) {}

	/**
	 * @param array<string, mixed> $guest
	 */
	public function anonymize( array $guest ): bool {
		$guest_id = (int) ( $guest['guest_id'] ?? 0 );
		if ( $guest_id <= 0 ) {
			return false;
		}

		if ( RetentionPolicy::ERASED_GUEST_LABEL === (string) ( $guest['display_name'] ?? '' ) ) {
			return true;
		}

		$this->photos->erase_guest_photos( $guest_id );
		$this->tokens->revoke( $guest );

		return $this->guests->update(
			$guest_id,
			[
				'display_name'        => RetentionPolicy::ERASED_GUEST_LABEL,
				'email'               => '',
				'phone'               => '',
				'party_label'         => '',
				'rsvp_comment'        => '',
				'dietary_notes'       => '',
				'address_book_id'     => null,
				'invitation_status'   => 'cancelled',
				'archived_at_utc'     => UtcDateTime::now(),
				'token_hash'          => hash( 'sha256', 'erased:' . $guest_id ),
				'updated_at_utc'      => UtcDateTime::now(),
			]
		);
	}

	/**
	 * @param array<string, mixed> $guest
	 */
	public function is_anonymized( array $guest ): bool {
		return RetentionPolicy::ERASED_GUEST_LABEL === (string) ( $guest['display_name'] ?? '' )
			&& '' === (string) ( $guest['email'] ?? '' );
	}
}
