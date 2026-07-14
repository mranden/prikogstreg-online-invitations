<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Scheduling;

use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\SchedulerMeta;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectExpiration;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectExpireService;

/**
 * Schedules and processes project expiration jobs.
 */
final class ExpirationScheduler {

	private static bool $scanning = false;

	public function __construct(
		private ProjectRepository $projects,
		private ProjectExpireService $expire
	) {
		add_action( SchedulerMeta::EXPIRE_PROJECT, [ $this, 'handle_expire' ], 10, 1 );
		add_action( SchedulerMeta::SCAN_EXPIRATIONS, [ $this, 'scan_due_projects' ] );
		add_action( 'pks_oi_project_event_saved', [ $this, 'reschedule_project' ], 20, 1 );
		add_action( 'pks_oi_project_expiry_changed', [ $this, 'reschedule_project' ], 10, 1 );
		add_action( 'pks_oi_project_restricted', [ $this, 'unschedule_project' ], 10, 1 );
		add_action( 'pks_oi_project_expired', [ $this, 'unschedule_project' ], 10, 1 );

		ActionSchedulerBridge::register_sync_handler( SchedulerMeta::EXPIRE_PROJECT, [ $this, 'handle_expire' ] );
		ActionSchedulerBridge::register_sync_handler( SchedulerMeta::SCAN_EXPIRATIONS, [ $this, 'scan_due_projects' ] );
	}

	public function register(): void {
		if ( function_exists( 'as_schedule_single_action' )
			&& ! ActionSchedulerBridge::is_scheduled( SchedulerMeta::SCAN_EXPIRATIONS, [], 'scan:expirations' ) ) {
			ActionSchedulerBridge::schedule_single(
				SchedulerMeta::SCAN_EXPIRATIONS,
				[],
				time() + 120,
				'scan:expirations'
			);
		}
	}

	public function handle_expire( int $project_id ): void {
		$this->expire->expire_if_due( $project_id );
	}

	public function scan_due_projects(): void {
		if ( self::$scanning ) {
			return;
		}

		self::$scanning = true;

		try {
			foreach ( $this->projects->list_active_past_expiry() as $project ) {
				$this->expire->expire_project( $project );
			}

			if ( ! function_exists( 'as_schedule_single_action' ) ) {
				return;
			}

			ActionSchedulerBridge::schedule_single(
				SchedulerMeta::SCAN_EXPIRATIONS,
				[],
				time() + DAY_IN_SECONDS,
				'scan:expirations:' . gmdate( 'Y-m-d' )
			);
		} finally {
			self::$scanning = false;
		}
	}

	public function reschedule_project( int $project_id ): void {
		$project = $this->projects->find_by_id( $project_id );
		if ( ! is_array( $project ) ) {
			return;
		}

		$expires_at = ProjectExpiration::recalculate_stored_expiry( $project );
		$this->projects->update(
			$project_id,
			[ 'expires_at_utc' => $expires_at ]
		);

		$this->unschedule_project( $project_id );

		if ( null === $expires_at || '' === $expires_at || ! ProjectExpiration::should_schedule( $project ) ) {
			return;
		}

		$timestamp = strtotime( $expires_at . ' UTC' );
		if ( false === $timestamp ) {
			return;
		}

		if ( $timestamp <= time() ) {
			$this->expire->expire_if_due( $project_id );

			return;
		}

		ActionSchedulerBridge::schedule_single(
			SchedulerMeta::EXPIRE_PROJECT,
			[ $project_id ],
			$timestamp,
			'expire:' . $project_id
		);
	}

	public function unschedule_project( int $project_id ): void {
		ActionSchedulerBridge::unschedule( SchedulerMeta::EXPIRE_PROJECT, [ $project_id ] );
	}
}
