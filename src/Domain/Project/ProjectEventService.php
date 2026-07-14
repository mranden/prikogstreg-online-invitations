<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

use PrikOgStreg\OnlineInvitations\Database\Repositories\EventRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

/**
 * Persists structured event fields on the project row.
 */
final class ProjectEventService {

	/** @var list<string> */
	public const ALLOWED_FIELDS = [
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
		'rsvp_deadline_utc',
		'timezone',
		'organiser_display_name',
		'public_contact_email',
		'public_contact_phone',
	];

	public function __construct(
		private ProjectRepository $projects,
		private EventRepository $events
	) {}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $input
	 * @return array{success:bool,error?:string}
	 */
	public function save_event_details( array $project, array $input ): array {
		if ( ! ProjectEntitlement::can_edit_project( $project ) ) {
			return [ 'success' => false, 'error' => 'entitlement_denied' ];
		}

		$data = $this->normalize_input( $input );
		if ( isset( $data['error'] ) ) {
			return [ 'success' => false, 'error' => (string) $data['error'] ];
		}

		$expires_at = ProjectExpiration::recalculate_stored_expiry( array_merge( $project, $data ) );
		if ( null !== $expires_at ) {
			$data['expires_at_utc'] = $expires_at;
		}

		$this->projects->update( (int) $project['project_id'], $data );
		$this->record_event( (int) $project['project_id'], 'project_event_saved' );
		do_action( 'pks_oi_project_event_saved', (int) $project['project_id'] );

		return [ 'success' => true ];
	}

	/**
	 * Support/admin save — skips customer entitlement but keeps validation.
	 *
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $input
	 * @return array{success:bool,error?:string}
	 */
	public function save_event_details_for_support( array $project, array $input ): array {
		$data = $this->normalize_input( $input );
		if ( isset( $data['error'] ) ) {
			return [ 'success' => false, 'error' => (string) $data['error'] ];
		}

		$expires_at = ProjectExpiration::recalculate_stored_expiry( array_merge( $project, $data ) );
		if ( null !== $expires_at ) {
			$data['expires_at_utc'] = $expires_at;
		}

		$this->projects->update( (int) $project['project_id'], $data );
		do_action( 'pks_oi_project_event_saved', (int) $project['project_id'] );

		return [ 'success' => true ];
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	private function normalize_input( array $input ): array {
		$data = [];

		foreach ( self::ALLOWED_FIELDS as $field ) {
			if ( ! array_key_exists( $field, $input ) ) {
				continue;
			}

			$value = $input[ $field ];
			if ( in_array( $field, [ 'event_start_utc', 'event_end_utc', 'rsvp_deadline_utc' ], true ) ) {
				$converted = $this->to_utc_datetime( (string) $value, (string) ( $input['timezone'] ?? $input['existing_timezone'] ?? 'Europe/Copenhagen' ) );
				if ( null === $converted && '' !== trim( (string) $value ) ) {
					return [ 'error' => 'invalid_datetime' ];
				}
				$data[ $field ] = $converted;
				continue;
			}

			if ( 'practical_info' === $field ) {
				$data[ $field ] = sanitize_textarea_field( (string) $value );
				continue;
			}

			if ( 'public_contact_email' === $field ) {
				$email = sanitize_email( (string) $value );
				$data[ $field ] = '' !== $email ? $email : null;
				continue;
			}

			$data[ $field ] = sanitize_text_field( (string) $value );
		}

		if ( isset( $data['event_title'] ) && '' === trim( (string) $data['event_title'] ) ) {
			return [ 'error' => 'missing_event_title' ];
		}

		return $data;
	}

	private function to_utc_datetime( string $local_value, string $timezone ): ?string {
		$local_value = trim( $local_value );
		if ( '' === $local_value ) {
			return null;
		}

		try {
			$zone = new \DateTimeZone( '' !== $timezone ? $timezone : 'Europe/Copenhagen' );
			$dt   = new \DateTimeImmutable( $local_value, $zone );

			return $dt->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		} catch ( \Exception $exception ) {
			return null;
		}
	}

	private function record_event( int $project_id, string $event_type ): void {
		$this->events->insert(
			[
				'project_id'    => $project_id,
				'actor_type'    => 'customer',
				'event_type'    => $event_type,
				'metadata_json' => '{}',
				'created_at_utc' => UtcDateTime::now(),
			]
		);
	}
}
