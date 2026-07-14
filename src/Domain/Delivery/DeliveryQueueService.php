<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Delivery;

use PrikOgStreg\OnlineInvitations\Database\Repositories\DeliveryRepository;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryType;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\SchedulerMeta;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectMeta;
use PrikOgStreg\OnlineInvitations\Scheduling\ActionSchedulerBridge;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

/**
 * Inserts delivery rows and schedules Action Scheduler send jobs.
 */
final class DeliveryQueueService {

	public function __construct(
		private DeliveryRepository $deliveries
	) {}

	/**
	 * @param array<string, mixed> $guest
	 */
	public function queue_rsvp_confirmation( array $guest, string $response_signature ): bool {
		$email = trim( (string) ( $guest['email'] ?? '' ) );
		if ( '' === $email ) {
			return false;
		}

		return $this->queue(
			(int) ( $guest['project_id'] ?? 0 ),
			(int) ( $guest['guest_id'] ?? 0 ),
			DeliveryType::RSVP_CONFIRMATION,
			'rsvp_confirm:' . (int) ( $guest['guest_id'] ?? 0 ) . ':' . $response_signature,
			$email
		) > 0;
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $guest
	 */
	public function queue_organizer_notification( array $project, array $guest, string $response_signature ): bool {
		$recipient = $this->organizer_recipient( $project );
		if ( '' === $recipient ) {
			return false;
		}

		return $this->queue(
			(int) ( $project['project_id'] ?? 0 ),
			(int) ( $guest['guest_id'] ?? 0 ),
			DeliveryType::ORGANIZER_RSVP,
			'organizer_rsvp:' . (int) ( $guest['guest_id'] ?? 0 ) . ':' . $response_signature,
			$recipient
		) > 0;
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public function queue_welcome( array $project ): int {
		$recipient = $this->owner_recipient( $project );
		if ( '' === $recipient ) {
			return 0;
		}

		return $this->queue(
			(int) $project['project_id'],
			0,
			DeliveryType::WELCOME,
			'welcome:' . (int) $project['project_id'],
			$recipient
		);
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public function queue_demo( array $project, string $scope ): int {
		$recipient = $this->owner_recipient( $project );
		if ( '' === $recipient ) {
			return 0;
		}

		return $this->queue(
			(int) $project['project_id'],
			0,
			DeliveryType::DEMO,
			'demo:' . (int) $project['project_id'] . ':' . $scope,
			$recipient,
			time() + 5
		);
	}

	/**
	 * @param array<string, mixed> $guest
	 */
	public function queue_guest_invitation( array $guest, string $scope ): int {
		$email = sanitize_email( (string) ( $guest['email'] ?? '' ) );
		if ( '' === $email ) {
			return 0;
		}

		return $this->queue(
			(int) ( $guest['project_id'] ?? 0 ),
			(int) ( $guest['guest_id'] ?? 0 ),
			DeliveryType::GUEST_INVITATION,
			'guest_invite:' . (int) ( $guest['guest_id'] ?? 0 ) . ':' . $scope,
			$email
		);
	}

	/**
	 * @param array<string, mixed> $guest
	 */
	public function queue_rsvp_reminder( array $guest, string $deadline_date, int $schedule_at ): int {
		$email = sanitize_email( (string) ( $guest['email'] ?? '' ) );
		if ( '' === $email ) {
			return 0;
		}

		return $this->queue(
			(int) ( $guest['project_id'] ?? 0 ),
			(int) ( $guest['guest_id'] ?? 0 ),
			DeliveryType::RSVP_REMINDER,
			'reminder:' . (int) ( $guest['project_id'] ?? 0 ) . ':' . (int) ( $guest['guest_id'] ?? 0 ) . ':' . $deadline_date,
			$email,
			$schedule_at
		);
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public function queue_photo_notification( array $project, int $guest_id, int $photo_id ): bool {
		$recipient = $this->organizer_recipient( $project );
		if ( '' === $recipient || $photo_id <= 0 ) {
			return false;
		}

		return $this->queue(
			(int) ( $project['project_id'] ?? 0 ),
			$guest_id > 0 ? $guest_id : 0,
			DeliveryType::PHOTO_NOTIFICATION,
			'photo_notify:' . $photo_id,
			$recipient
		) > 0;
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $guest
	 */
	public function queue_photo_share_invite( array $project, array $guest ): bool {
		$email = sanitize_email( (string) ( $guest['email'] ?? '' ) );
		if ( '' === $email ) {
			return false;
		}

		return $this->queue(
			(int) ( $project['project_id'] ?? 0 ),
			(int) ( $guest['guest_id'] ?? 0 ),
			DeliveryType::PHOTO_SHARE_INVITE,
			'photo_share:' . (int) ( $guest['guest_id'] ?? 0 ) . ':' . gmdate( 'Ymd' ),
			$email
		) > 0;
	}

	public function cancel_queued_for_project( int $project_id, ?string $delivery_type = null ): int {
		$rows = $this->deliveries->list_by_project_and_status( $project_id, DeliveryStatus::QUEUED, $delivery_type );
		$count = 0;
		foreach ( $rows as $row ) {
			$delivery_id = (int) ( $row['delivery_id'] ?? 0 );
			if ( $delivery_id <= 0 ) {
				continue;
			}
			$this->deliveries->update(
				$delivery_id,
				[
					'status'        => DeliveryStatus::CANCELLED,
					'failed_at_utc' => UtcDateTime::now(),
					'last_error_code' => 'cancelled',
				]
			);
			$this->unschedule_delivery( $row );
			++$count;
		}

		return $count;
	}

	public function cancel_all_project_jobs( int $project_id ): int {
		$count = $this->cancel_queued_for_project( $project_id );

		ActionSchedulerBridge::unschedule( SchedulerMeta::EXPIRE_PROJECT, [ $project_id ] );
		ActionSchedulerBridge::unschedule( SchedulerMeta::RESCHEDULE_REMINDERS, [ $project_id ] );
		ActionSchedulerBridge::unschedule( ProjectMeta::WELCOME_ACTION_HOOK, [ $project_id ] );

		foreach ( $this->deliveries->list_by_project( $project_id ) as $row ) {
			$this->unschedule_delivery( $row );
		}

		return $count;
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function unschedule_delivery( array $row ): void {
		$delivery_id = (int) ( $row['delivery_id'] ?? 0 );
		if ( $delivery_id <= 0 ) {
			return;
		}

		$type = (string) ( $row['delivery_type'] ?? '' );
		$hook = DeliveryType::RSVP_REMINDER === $type
			? SchedulerMeta::SEND_REMINDER
			: SchedulerMeta::SEND_INVITATION;

		ActionSchedulerBridge::unschedule( $hook, [ $delivery_id ] );
	}

	private function queue(
		int $project_id,
		int $guest_id,
		string $delivery_type,
		string $idempotency_key,
		string $recipient,
		?int $schedule_at = null
	): int {
		if ( $project_id <= 0 || '' === $idempotency_key ) {
			return 0;
		}

		$existing = $this->deliveries->find_by_idempotency_key( $idempotency_key );
		if ( is_array( $existing ) ) {
			if ( DeliveryStatus::SENT === (string) ( $existing['status'] ?? '' ) ) {
				return 0;
			}

			return (int) ( $existing['delivery_id'] ?? 0 );
		}

		$schedule_at ??= time();
		$delivery_id = $this->deliveries->insert(
			[
				'project_id'       => $project_id,
				'guest_id'         => $guest_id > 0 ? $guest_id : null,
				'delivery_type'    => $delivery_type,
				'idempotency_key'  => $idempotency_key,
				'recipient_hash'   => hash( 'sha256', strtolower( trim( $recipient ) ) ),
				'status'           => DeliveryStatus::QUEUED,
				'scheduled_at_utc' => gmdate( 'Y-m-d H:i:s', $schedule_at ),
			]
		);

		if ( $delivery_id <= 0 ) {
			return 0;
		}

		$hook = DeliveryType::RSVP_REMINDER === $delivery_type
			? SchedulerMeta::SEND_REMINDER
			: SchedulerMeta::SEND_INVITATION;

		ActionSchedulerBridge::schedule_single(
			$hook,
			[ $delivery_id ],
			$schedule_at,
			'send:' . $delivery_id
		);

		return $delivery_id;
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function organizer_recipient( array $project ): string {
		$contact = sanitize_email( (string) ( $project['public_contact_email'] ?? '' ) );
		if ( '' !== $contact ) {
			return $contact;
		}

		return $this->owner_recipient( $project );
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function owner_recipient( array $project ): string {
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
}
