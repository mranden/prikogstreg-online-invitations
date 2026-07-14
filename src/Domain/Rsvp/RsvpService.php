<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Rsvp;

use PrikOgStreg\OnlineInvitations\Database\Repositories\EventRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestSendTokenStore;
use PrikOgStreg\OnlineInvitations\Domain\Guest\InvitationStatus;
use PrikOgStreg\OnlineInvitations\Domain\Guest\RsvpStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicEntitlement;
use PrikOgStreg\OnlineInvitations\Public\TokenResolution;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

/**
 * Personal and generic-link RSVP submission.
 */
final class RsvpService {

	public const MAX_ATTENDEE_COUNT = 50;

	public const GENERIC_RATE_WINDOW = 3600;

	public const GENERIC_RATE_MAX = 10;

	public function __construct(
		private GuestRepository $guests,
		private EventRepository $events,
		private DeliveryQueueService $delivery_queue
	) {}

	/**
	 * @param array<string, mixed> $input
	 * @return array{success:bool,error?:string,message?:string,guest?:array<string,mixed>,invitation_url?:string,replayed?:bool}
	 */
	public function submit_personal( TokenResolution $resolution, array $input, string $idempotency_key ): array {
		$project = $resolution->project();
		$guest   = $resolution->guest();

		if ( ! $resolution->is_personal() || ! is_array( $guest ) ) {
			return $this->error( 'invalid_context' );
		}

		$access = $this->assert_submission_allowed( $project, $guest );
		if ( ! $access['success'] ) {
			return $access;
		}

		$parsed = $this->parse_response_input( $project, $input, $guest );
		if ( isset( $parsed['error'] ) ) {
			return $this->error( (string) $parsed['error'] );
		}

		/** @var array<string, mixed> $update */
		$update = $parsed['update'];
		$signature = $this->response_signature( $update );

		if ( $this->is_replay( $idempotency_key, (int) $guest['guest_id'], $signature, $guest ) ) {
			return [
				'success'  => true,
				'message'  => $this->success_message( $update ),
				'guest'    => $guest,
				'replayed' => true,
			];
		}

		if ( ! $this->has_response_changed( $guest, $update ) ) {
			$this->remember_idempotency( $idempotency_key, (int) $guest['guest_id'], $signature );

			return [
				'success'  => true,
				'message'  => $this->success_message( $update ),
				'guest'    => $guest,
				'replayed' => true,
			];
		}

		$was_pending = RsvpStatus::PENDING === (string) ( $guest['rsvp_status'] ?? RsvpStatus::PENDING );
		$ok          = $this->guests->update( (int) $guest['guest_id'], $update );
		if ( ! $ok ) {
			return $this->error( 'save_failed' );
		}

		$updated = $this->guests->find_by_id( (int) $guest['guest_id'] );
		if ( ! is_array( $updated ) ) {
			return $this->error( 'save_failed' );
		}

		$this->record_rsvp_event(
			$project,
			$updated,
			$was_pending ? 'guest_rsvp_submitted' : 'guest_rsvp_changed',
			(string) ( $guest['rsvp_status'] ?? RsvpStatus::PENDING ),
			(string) $update['rsvp_status']
		);

		$this->delivery_queue->queue_rsvp_confirmation( $updated, $signature );
		$this->delivery_queue->queue_organizer_notification( $project, $updated, $signature );
		$this->remember_idempotency( $idempotency_key, (int) $guest['guest_id'], $signature );

		do_action( 'pks_oi_guest_rsvp_saved', (int) $guest['guest_id'], (int) $project['project_id'] );

		return [
			'success' => true,
			'message' => $this->success_message( $update ),
			'guest'   => $updated,
		];
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array{success:bool,error?:string,message?:string,guest?:array<string,mixed>,invitation_url?:string,replayed?:bool}
	 */
	public function submit_generic( TokenResolution $resolution, array $input, string $idempotency_key, string $client_key ): array {
		if ( ! $resolution->is_generic() ) {
			return $this->error( 'invalid_context' );
		}

		$project = $resolution->project();
		$access  = $this->assert_submission_allowed( $project, null );
		if ( ! $access['success'] ) {
			return $access;
		}

		if ( $this->is_generic_rate_limited( (int) $project['project_id'], $client_key ) ) {
			return $this->error( 'rate_limited' );
		}

		$name = RsvpSanitizer::display_name( (string) ( $input['display_name'] ?? '' ) );
		if ( '' === $name ) {
			return $this->error( 'missing_display_name' );
		}

		$email = RsvpSanitizer::email( (string) ( $input['email'] ?? '' ) );
		if ( null === $email && '' !== trim( (string) ( $input['email'] ?? '' ) ) ) {
			return $this->error( 'invalid_email' );
		}

		$parsed = $this->parse_response_input( $project, $input, null );
		if ( isset( $parsed['error'] ) ) {
			return $this->error( (string) $parsed['error'] );
		}

		/** @var array<string, mixed> $update */
		$update = $parsed['update'];
		$signature = $this->response_signature( $update );

		$replay_guest_id = $this->replay_guest_id_for_key( $idempotency_key );
		if ( $replay_guest_id > 0 ) {
			$existing = $this->guests->find_by_id( $replay_guest_id );
			if ( is_array( $existing ) && (int) ( $existing['project_id'] ?? 0 ) === (int) $project['project_id'] ) {
				return [
					'success'  => true,
					'message'  => $this->success_message( $update ),
					'guest'    => $existing,
					'replayed' => true,
				];
			}
		}

		$pair = InvitationToken::generate();
		$guest_id = $this->guests->insert(
			array_merge(
				$update,
				[
					'project_id'          => (int) $project['project_id'],
					'display_name'        => $name,
					'email'               => $email,
					'token_hash'          => $pair['hash'],
					'token_version'       => 1,
					'invitation_status'   => InvitationStatus::OPENED,
					'is_generic_response' => 1,
				]
			)
		);

		if ( $guest_id <= 0 ) {
			return $this->error( 'create_failed' );
		}

		$guest = $this->guests->find_by_id( $guest_id );
		if ( ! is_array( $guest ) ) {
			return $this->error( 'create_failed' );
		}

		$this->record_rsvp_event( $project, $guest, 'generic_rsvp_created', RsvpStatus::PENDING, (string) $update['rsvp_status'] );
		$this->delivery_queue->queue_rsvp_confirmation( $guest, $signature );
		$this->delivery_queue->queue_organizer_notification( $project, $guest, $signature );
		$this->remember_idempotency( $idempotency_key, $guest_id, $signature );
		$this->record_generic_attempt( (int) $project['project_id'], $client_key );

		do_action( 'pks_oi_generic_rsvp_created', $guest_id, (int) $project['project_id'] );

		GuestSendTokenStore::remember( $guest_id, $pair['raw'] );

		return [
			'success'         => true,
			'message'         => $this->success_message( $update ),
			'guest'           => $guest,
			'invitation_url'  => InvitationToken::public_url( $pair['raw'] ),
		];
	}

	/**
	 * @param array<string, mixed>      $project
	 * @param array<string, mixed>|null $guest
	 * @return array{success:bool,error?:string}
	 */
	private function assert_submission_allowed( array $project, ?array $guest ): array {
		if ( ! PublicEntitlement::is_publicly_available( $project ) ) {
			return $this->error( 'unavailable' );
		}

		if ( is_array( $guest ) && ! PublicEntitlement::is_guest_accessible( $guest ) ) {
			return $this->error( 'unavailable' );
		}

		if ( ! RsvpDeadlinePolicy::is_open( $project ) ) {
			return $this->error( 'deadline_closed' );
		}

		return [ 'success' => true ];
	}

	/**
	 * @param array<string, mixed>      $project
	 * @param array<string, mixed>      $input
	 * @param array<string, mixed>|null $existing
	 * @return array{update:array<string,mixed>}|array{error:string}
	 */
	private function parse_response_input( array $project, array $input, ?array $existing ): array {
		$attending_raw = strtolower( trim( (string) ( $input['attending'] ?? '' ) ) );
		if ( ! in_array( $attending_raw, [ 'yes', 'no', '1', '0', 'true', 'false' ], true ) ) {
			return [ 'error' => 'invalid_attending' ];
		}

		$attending = in_array( $attending_raw, [ 'yes', '1', 'true' ], true );
		$status    = $attending ? RsvpStatus::ATTENDING : RsvpStatus::DECLINED;

		$update = [
			'rsvp_status'      => $status,
			'responded_at_utc' => UtcDateTime::now(),
		];

		if ( $attending && ! empty( $project['attendee_count_enabled'] ) ) {
			if ( ! array_key_exists( 'attendee_count', $input ) || '' === (string) $input['attendee_count'] ) {
				return [ 'error' => 'missing_attendee_count' ];
			}

			$count = (int) $input['attendee_count'];
			if ( $count < 1 || $count > self::MAX_ATTENDEE_COUNT ) {
				return [ 'error' => 'invalid_attendee_count' ];
			}

			$update['attendee_count'] = $count;
		} elseif ( $attending ) {
			$update['attendee_count'] = max( 1, (int) ( $input['attendee_count'] ?? 1 ) );
		} else {
			$update['attendee_count'] = null;
		}

		if ( ! empty( $project['comment_enabled'] ) && array_key_exists( 'rsvp_comment', $input ) ) {
			$update['rsvp_comment'] = RsvpSanitizer::comment( (string) $input['rsvp_comment'] );
		} elseif ( is_array( $existing ) ) {
			$update['rsvp_comment'] = $existing['rsvp_comment'] ?? null;
		} else {
			$update['rsvp_comment'] = null;
		}

		if ( ! empty( $project['dietary_notes_enabled'] ) && array_key_exists( 'dietary_notes', $input ) ) {
			$update['dietary_notes'] = RsvpSanitizer::dietary_notes( (string) $input['dietary_notes'] );
		} elseif ( is_array( $existing ) ) {
			$update['dietary_notes'] = $existing['dietary_notes'] ?? null;
		} else {
			$update['dietary_notes'] = null;
		}

		return [ 'update' => $update ];
	}

	/**
	 * @param array<string, mixed> $guest
	 * @param array<string, mixed> $update
	 */
	private function has_response_changed( array $guest, array $update ): bool {
		foreach ( [ 'rsvp_status', 'attendee_count', 'rsvp_comment', 'dietary_notes' ] as $field ) {
			$old = $guest[ $field ] ?? null;
			$new = $update[ $field ] ?? null;

			if ( '' === $old ) {
				$old = null;
			}
			if ( '' === $new ) {
				$new = null;
			}

			if ( $old !== $new ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $update
	 */
	private function response_signature( array $update ): string {
		$payload = wp_json_encode(
			[
				'rsvp_status'    => (string) ( $update['rsvp_status'] ?? '' ),
				'attendee_count' => $update['attendee_count'] ?? null,
				'rsvp_comment'   => (string) ( $update['rsvp_comment'] ?? '' ),
				'dietary_notes'  => (string) ( $update['dietary_notes'] ?? '' ),
			]
		);

		return hash( 'sha256', is_string( $payload ) ? $payload : '' );
	}

	/**
	 * @param array<string, mixed> $guest
	 */
	private function is_replay( string $idempotency_key, int $guest_id, string $signature, array $guest ): bool {
		$stored = $this->load_idempotency( $idempotency_key );
		if ( ! is_array( $stored ) ) {
			return false;
		}

		return (int) ( $stored['guest_id'] ?? 0 ) === $guest_id
			&& (string) ( $stored['signature'] ?? '' ) === $signature
			&& ! $this->has_response_changed( $guest, $this->guest_update_from_signature_context( $guest, $signature ) );
	}

	/**
	 * @param array<string, mixed> $guest
	 * @return array<string, mixed>
	 */
	private function guest_update_from_signature_context( array $guest, string $signature ): array {
		unset( $signature );

		return [
			'rsvp_status'    => $guest['rsvp_status'] ?? RsvpStatus::PENDING,
			'attendee_count' => $guest['attendee_count'] ?? null,
			'rsvp_comment'   => $guest['rsvp_comment'] ?? null,
			'dietary_notes'  => $guest['dietary_notes'] ?? null,
		];
	}

	private function remember_idempotency( string $idempotency_key, int $guest_id, string $signature ): void {
		$key = $this->idempotency_transient_key( $idempotency_key );
		if ( '' === $key ) {
			return;
		}

		set_transient(
			$key,
			[
				'guest_id'  => $guest_id,
				'signature' => $signature,
			],
			86400
		);
	}

	/**
	 * @return array{guest_id:int,signature:string}|null
	 */
	private function load_idempotency( string $idempotency_key ): ?array {
		$key = $this->idempotency_transient_key( $idempotency_key );
		if ( '' === $key ) {
			return null;
		}

		$stored = get_transient( $key );

		return is_array( $stored ) ? $stored : null;
	}

	private function replay_guest_id_for_key( string $idempotency_key ): int {
		$stored = $this->load_idempotency( $idempotency_key );

		return is_array( $stored ) ? (int) ( $stored['guest_id'] ?? 0 ) : 0;
	}

	private function idempotency_transient_key( string $idempotency_key ): string {
		$idempotency_key = trim( $idempotency_key );
		if ( '' === $idempotency_key || strlen( $idempotency_key ) > 128 ) {
			return '';
		}

		return 'pks_oi_rsvp_idem_' . hash( 'sha256', $idempotency_key );
	}

	private function is_generic_rate_limited( int $project_id, string $client_key ): bool {
		$key   = 'pks_oi_generic_rsvp_' . hash( 'sha256', $project_id . ':' . $client_key );
		$count = (int) get_transient( $key );

		return $count >= self::GENERIC_RATE_MAX;
	}

	private function record_generic_attempt( int $project_id, string $client_key ): void {
		$key   = 'pks_oi_generic_rsvp_' . hash( 'sha256', $project_id . ':' . $client_key );
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, self::GENERIC_RATE_WINDOW );
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $guest
	 */
	private function record_rsvp_event( array $project, array $guest, string $event_type, string $from_status, string $to_status ): void {
		$metadata = wp_json_encode(
			[
				'from'           => $from_status,
				'to'             => $to_status,
				'attendee_count' => $guest['attendee_count'] ?? null,
				'is_generic'     => ! empty( $guest['is_generic_response'] ),
			]
		);

		$this->events->insert(
			[
				'project_id'     => (int) $project['project_id'],
				'guest_id'       => (int) ( $guest['guest_id'] ?? 0 ),
				'actor_type'     => 'guest',
				'event_type'     => $event_type,
				'metadata_json'  => is_string( $metadata ) ? $metadata : '{}',
				'created_at_utc' => UtcDateTime::now(),
			]
		);
	}

	/**
	 * @param array<string, mixed> $update
	 */
	private function success_message( array $update ): string {
		if ( RsvpStatus::ATTENDING === (string) ( $update['rsvp_status'] ?? '' ) ) {
			return __( 'Thank you — your response has been recorded.', 'prikogstreg-online-invitations' );
		}

		return __( 'Thank you — we have recorded that you cannot attend.', 'prikogstreg-online-invitations' );
	}

	/**
	 * @return array{success:false,error:string}
	 */
	private function error( string $code ): array {
		return [ 'success' => false, 'error' => $code ];
	}
}
