<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Scheduling;

use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryType;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\SchedulerMeta;
use PrikOgStreg\OnlineInvitations\Domain\Guest\RsvpStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicEntitlement;

/**
 * Schedules and reschedules RSVP reminder deliveries.
 */
final class ReminderScheduler {

	public function __construct(
		private ProjectRepository $projects,
		private GuestRepository $guests,
		private DeliveryQueueService $queue
	) {
		add_action( 'pks_oi_project_event_saved', [ $this, 'maybe_reschedule' ], 10, 1 );
		add_action( 'pks_oi_project_published', [ $this, 'maybe_reschedule' ], 10, 1 );
		add_action( 'pks_oi_project_unpublished', [ $this, 'cancel_for_project' ], 10, 1 );
		add_action( SchedulerMeta::RESCHEDULE_REMINDERS, [ $this, 'reschedule' ], 10, 1 );
		ActionSchedulerBridge::register_sync_handler(
			SchedulerMeta::RESCHEDULE_REMINDERS,
			[ $this, 'reschedule' ]
		);
	}

	public function register(): void {
		// Hooks attached in constructor.
	}

	public function maybe_reschedule( int $project_id ): void {
		ActionSchedulerBridge::schedule_single(
			SchedulerMeta::RESCHEDULE_REMINDERS,
			[ $project_id ],
			time() + 10,
			'reschedule:' . $project_id . ':' . time()
		);
	}

	public function reschedule( int $project_id ): void {
		$project = $this->projects->find_by_id( $project_id );
		if ( ! is_array( $project ) || ! PublicEntitlement::is_publicly_available( $project ) ) {
			$this->cancel_for_project( $project_id );

			return;
		}

		$deadline = trim( (string) ( $project['rsvp_deadline_utc'] ?? '' ) );
		if ( '' === $deadline ) {
			$this->queue->cancel_queued_for_project( $project_id, DeliveryType::RSVP_REMINDER );

			return;
		}

		$this->queue->cancel_queued_for_project( $project_id, DeliveryType::RSVP_REMINDER );

		$deadline_date = substr( $deadline, 0, 10 );
		$offset_days   = max( 0, (int) ( $project['reminder_offset_days'] ?? 5 ) );
		$timestamp     = strtotime( $deadline . ' UTC' ) - ( $offset_days * 86400 );
		if ( false === $timestamp || $timestamp <= time() ) {
			return;
		}

		$guest_rows = $this->guests->list_by_project( $project_id );
		foreach ( $guest_rows as $guest ) {
			if ( RsvpStatus::PENDING !== (string) ( $guest['rsvp_status'] ?? RsvpStatus::PENDING ) ) {
				continue;
			}
			if ( '' === sanitize_email( (string) ( $guest['email'] ?? '' ) ) ) {
				continue;
			}
			if ( null !== ( $guest['archived_at_utc'] ?? null ) && '' !== (string) $guest['archived_at_utc'] ) {
				continue;
			}

			$this->queue->queue_rsvp_reminder( $guest, $deadline_date, $timestamp );
		}
	}

	public function cancel_for_project( int $project_id ): void {
		$this->queue->cancel_queued_for_project( $project_id, DeliveryType::RSVP_REMINDER );
		ActionSchedulerBridge::unschedule( SchedulerMeta::RESCHEDULE_REMINDERS, [ $project_id ] );
	}
}
