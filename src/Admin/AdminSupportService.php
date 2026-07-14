<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin;

use PrikOgStreg\OnlineInvitations\Database\Repositories\EventRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Domain\Guest\RsvpStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectEventService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectLifecycleAudit;

/**
 * Safe administrator support mutations with audit logging.
 */
final class AdminSupportService {

	public function __construct(
		private ProjectRepository $projects,
		private GuestRepository $guests,
		private ProjectEventService $events,
		private ProjectLifecycleAudit $audit
	) {}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $input
	 * @return array{success:bool,error?:string}
	 */
	public function save_event_details( array $project, array $input ): array {
		$project_id = (int) ( $project['project_id'] ?? 0 );
		if ( $project_id <= 0 ) {
			return [ 'success' => false, 'error' => 'invalid_project' ];
		}

		$before = $this->event_snapshot( $project );
		$result = $this->events->save_event_details_for_support( $project, $input );
		if ( ! ( $result['success'] ?? false ) ) {
			return $result;
		}

		$after = $this->projects->find_by_id( $project_id );
		$this->audit->record_admin(
			$project_id,
			'admin.event_details_changed',
			[
				'before' => wp_json_encode( $before ) ?: '{}',
				'after'  => wp_json_encode( is_array( $after ) ? $this->event_snapshot( $after ) : [] ) ?: '{}',
			]
		);

		return [ 'success' => true ];
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $guest
	 * @param array<string, mixed> $input
	 * @return array{success:bool,error?:string}
	 */
	public function update_guest( array $project, array $guest, array $input ): array {
		$project_id = (int) ( $project['project_id'] ?? 0 );
		$guest_id   = (int) ( $guest['guest_id'] ?? 0 );
		if ( $project_id <= 0 || $guest_id <= 0 || (int) ( $guest['project_id'] ?? 0 ) !== $project_id ) {
			return [ 'success' => false, 'error' => 'forbidden' ];
		}

		$data = [];
		if ( array_key_exists( 'display_name', $input ) ) {
			$name = trim( sanitize_text_field( (string) $input['display_name'] ) );
			if ( '' === $name ) {
				return [ 'success' => false, 'error' => 'missing_display_name' ];
			}
			$data['display_name'] = $name;
		}

		if ( array_key_exists( 'email', $input ) ) {
			$email = trim( (string) $input['email'] );
			if ( '' !== $email ) {
				$email = sanitize_email( $email );
				if ( '' === $email ) {
					return [ 'success' => false, 'error' => 'invalid_email' ];
				}
			} else {
				$email = null;
			}
			$data['email'] = $email;
		}

		if ( array_key_exists( 'rsvp_status', $input ) ) {
			$rsvp = sanitize_key( (string) $input['rsvp_status'] );
			if ( ! in_array( $rsvp, [ RsvpStatus::PENDING, RsvpStatus::ATTENDING, RsvpStatus::DECLINED ], true ) ) {
				return [ 'success' => false, 'error' => 'invalid_rsvp_status' ];
			}
			$data['rsvp_status'] = $rsvp;
		}

		if ( array_key_exists( 'attendee_count', $input ) && '' !== (string) $input['attendee_count'] ) {
			$count = (int) $input['attendee_count'];
			if ( $count < 1 || $count > 50 ) {
				return [ 'success' => false, 'error' => 'invalid_attendee_count' ];
			}
			$data['attendee_count'] = $count;
		}

		if ( [] === $data ) {
			return [ 'success' => false, 'error' => 'no_changes' ];
		}

		$before_rsvp = (string) ( $guest['rsvp_status'] ?? '' );
		$ok          = $this->guests->update( $guest_id, $data );
		if ( ! $ok ) {
			return [ 'success' => false, 'error' => 'update_failed' ];
		}

		$this->audit->record_admin(
			$project_id,
			'admin.guest_updated',
			[
				'guest_id' => $guest_id,
				'fields'   => implode( ',', array_keys( $data ) ),
				'rsvp_from'=> $before_rsvp,
				'rsvp_to'  => (string) ( $data['rsvp_status'] ?? $before_rsvp ),
			]
		);

		return [ 'success' => true ];
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array<string, string|null>
	 */
	private function event_snapshot( array $project ): array {
		$snapshot = [];
		foreach ( ProjectEventService::ALLOWED_FIELDS as $field ) {
			$value = $project[ $field ] ?? null;
			$snapshot[ $field ] = is_scalar( $value ) || null === $value ? ( is_string( $value ) ? substr( $value, 0, 200 ) : $value ) : null;
		}

		return $snapshot;
	}
}
