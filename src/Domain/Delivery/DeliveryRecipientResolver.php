<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Delivery;

use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestSendTokenStore;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoShareTokenService;
use PrikOgStreg\OnlineInvitations\Domain\Guest\RsvpStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicEntitlement;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;

/**
 * Resolves recipient e-mail and send context from delivery rows.
 */
final class DeliveryRecipientResolver {

	public function __construct(
		private ProjectRepository $projects,
		private GuestRepository $guests
	) {}

	/**
	 * @param array<string, mixed> $delivery
	 * @return array{success:bool,error?:string,email?:string,project?:array<string,mixed>,guest?:array<string,mixed>|null,invitation_url?:string,account_url?:string}
	 */
	public function resolve( array $delivery ): array {
		$project_id = (int) ( $delivery['project_id'] ?? 0 );
		$project    = $this->projects->find_by_id( $project_id );
		if ( ! is_array( $project ) ) {
			return [ 'success' => false, 'error' => 'project_missing' ];
		}

		$guest = null;
		$guest_id = (int) ( $delivery['guest_id'] ?? 0 );
		if ( $guest_id > 0 ) {
			$guest = $this->guests->find_by_id_for_project( $guest_id, $project_id );
		}

		$type = (string) ( $delivery['delivery_type'] ?? '' );

		if ( in_array( $type, [ DeliveryType::GUEST_INVITATION, DeliveryType::RSVP_REMINDER, DeliveryType::RSVP_CONFIRMATION, DeliveryType::PHOTO_SHARE_INVITE ], true ) ) {
			if ( ! is_array( $guest ) ) {
				return [ 'success' => false, 'error' => 'guest_missing' ];
			}
			if ( ! PublicEntitlement::is_guest_accessible( $guest ) ) {
				return [ 'success' => false, 'error' => 'guest_revoked' ];
			}
		}

		if ( DeliveryType::RSVP_REMINDER === $type ) {
			$skip = $this->should_skip_reminder( $project, $guest );
			if ( null !== $skip ) {
				return [ 'success' => false, 'error' => $skip ];
			}
		}

		$email = $this->resolve_email( $project, $guest, $type );
		if ( '' === $email ) {
			return [ 'success' => false, 'error' => 'no_recipient' ];
		}

		$context = [
			'success' => true,
			'email'   => $email,
			'project' => $project,
			'guest'   => $guest,
		];

		if ( is_array( $guest ) && in_array( $type, [ DeliveryType::GUEST_INVITATION, DeliveryType::RSVP_REMINDER, DeliveryType::RSVP_CONFIRMATION ], true ) ) {
			$url = GuestSendTokenStore::invitation_url( (int) $guest['guest_id'] );
			if ( '' === $url ) {
				return [ 'success' => false, 'error' => 'invitation_url_unavailable' ];
			}
			$context['invitation_url'] = $url;
		}

		if ( in_array( $type, [ DeliveryType::WELCOME, DeliveryType::DEMO, DeliveryType::ORGANIZER_RSVP, DeliveryType::PHOTO_NOTIFICATION ], true ) ) {
			$context['account_url'] = $this->account_url( $project_id, $type );
		}

		if ( DeliveryType::PHOTO_SHARE_INVITE === $type ) {
			$share_tokens = new PhotoShareTokenService( $this->projects );
			$raw          = $share_tokens->resolve_raw_token( $project );
			if ( null === $raw ) {
				return [ 'success' => false, 'error' => 'photo_share_unavailable' ];
			}
			$context['photo_share_url'] = PhotoShareTokenService::public_url( $raw );
		}

		return $context;
	}

	/**
	 * @param array<string, mixed>      $project
	 * @param array<string, mixed>|null $guest
	 */
	private function resolve_email( array $project, ?array $guest, string $type ): string {
		if ( DeliveryType::ORGANIZER_RSVP === $type ) {
			return $this->organizer_email( $project );
		}

		if ( DeliveryType::PHOTO_NOTIFICATION === $type ) {
			return $this->organizer_email( $project );
		}

		if ( DeliveryType::WELCOME === $type || DeliveryType::DEMO === $type ) {
			return $this->owner_email( $project );
		}

		if ( is_array( $guest ) ) {
			$email = sanitize_email( (string) ( $guest['email'] ?? '' ) );

			return '' !== $email ? $email : '';
		}

		return '';
	}

	/**
	 * @param array<string, mixed>      $project
	 * @param array<string, mixed>|null $guest
	 */
	private function should_skip_reminder( array $project, ?array $guest ): ?string {
		if ( ! PublicEntitlement::is_publicly_available( $project ) ) {
			return 'project_unavailable';
		}

		$deadline = trim( (string) ( $project['rsvp_deadline_utc'] ?? '' ) );
		if ( '' === $deadline ) {
			return 'no_deadline';
		}

		if ( ! is_array( $guest ) ) {
			return 'guest_missing';
		}

		$rsvp = (string) ( $guest['rsvp_status'] ?? RsvpStatus::PENDING );
		if ( RsvpStatus::PENDING !== $rsvp ) {
			return 'already_responded';
		}

		$email = sanitize_email( (string) ( $guest['email'] ?? '' ) );
		if ( '' === $email ) {
			return 'no_email';
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function organizer_email( array $project ): string {
		$contact = sanitize_email( (string) ( $project['public_contact_email'] ?? '' ) );
		if ( '' !== $contact ) {
			return $contact;
		}

		return $this->owner_email( $project );
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function owner_email( array $project ): string {
		$user_id = (int) ( $project['user_id'] ?? 0 );
		if ( $user_id <= 0 || ! function_exists( 'get_userdata' ) ) {
			return '';
		}

		$user = get_userdata( $user_id );
		if ( ! is_object( $user ) || ! isset( $user->user_email ) ) {
			return '';
		}

		$email = sanitize_email( (string) $user->user_email );

		return '' !== $email ? $email : '';
	}

	private function account_url( int $project_id, string $type ): string {
		unset( $type );

		if ( ! class_exists( \PrikOgStreg\OnlineInvitations\MyAccount\Endpoints::class ) ) {
			return '';
		}

		return \PrikOgStreg\OnlineInvitations\MyAccount\Endpoints::project_url( $project_id );
	}
}
