<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Scheduling;

use PrikOgStreg\OnlineInvitations\Database\Repositories\DeliveryRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryStatus;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryType;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectEntitlement;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectMeta;
use PrikOgStreg\OnlineInvitations\MyAccount\Endpoints;

/**
 * Queues the project welcome e-mail exactly once after usable state exists.
 */
final class WelcomeScheduler {

	public function __construct(
		private ProjectRepository $projects,
		private DeliveryRepository $deliveries,
		private DeliveryQueueService $queue
	) {
		add_action( ProjectMeta::WELCOME_ACTION_HOOK, [ $this, 'handle_scheduled_send' ], 10, 1 );
		add_action( 'pks_oi_delivery_sent', [ $this, 'maybe_mark_welcome_sent' ], 10, 2 );
		ActionSchedulerBridge::register_sync_handler(
			ProjectMeta::WELCOME_ACTION_HOOK,
			[ $this, 'handle_scheduled_send' ]
		);
	}

	public function register(): void {
		// Hook registered in constructor for testability.
	}

	public function queue_once( int $project_id ): bool {
		if ( $this->was_welcome_sent( $project_id ) ) {
			return false;
		}

		if ( $this->welcome_already_sent( $project_id ) ) {
			$this->mark_welcome_sent( $project_id );

			return false;
		}

		$project = $this->projects->find_by_id( $project_id );
		if ( ! is_array( $project ) || ! ProjectEntitlement::is_project_usable( $project ) ) {
			return false;
		}

		if ( function_exists( 'as_schedule_single_action' ) && function_exists( 'as_next_scheduled_action' ) ) {
			$hook = ProjectMeta::WELCOME_ACTION_HOOK;
			if ( as_next_scheduled_action( $hook, [ $project_id ], ProjectMeta::WELCOME_ACTION_GROUP ) ) {
				return false;
			}

			as_schedule_single_action(
				time() + 30,
				$hook,
				[ $project_id ],
				ProjectMeta::WELCOME_ACTION_GROUP,
				true,
				'welcome:' . $project_id
			);

			return true;
		}

		return $this->handle_scheduled_send( $project_id );
	}

	public function handle_scheduled_send( int $project_id ): bool {
		if ( $this->was_welcome_sent( $project_id ) ) {
			return false;
		}

		if ( $this->welcome_already_sent( $project_id ) ) {
			$this->mark_welcome_sent( $project_id );

			return false;
		}

		$project = $this->projects->find_by_id( $project_id );
		if ( ! is_array( $project ) || ! ProjectEntitlement::is_project_usable( $project ) ) {
			return false;
		}

		$delivery_id = $this->queue->queue_welcome( $project );
		if ( $delivery_id <= 0 ) {
			return false;
		}

		do_action( 'pks_oi_project_welcome_ready', $project_id, Endpoints::project_url( $project_id ) );

		return true;
	}

	public function maybe_mark_welcome_sent( int $delivery_id, string $delivery_type ): void {
		if ( DeliveryType::WELCOME !== $delivery_type ) {
			return;
		}

		$delivery = $this->deliveries->find_by_id( $delivery_id );
		if ( ! is_array( $delivery ) ) {
			return;
		}

		$this->mark_welcome_sent( (int) ( $delivery['project_id'] ?? 0 ) );
	}

	public function mark_welcome_sent( int $project_id ): void {
		if ( $project_id <= 0 ) {
			return;
		}

		update_option( ProjectMeta::WELCOME_SENT_OPTION_PREFIX . $project_id, time(), false );
	}

	public function was_welcome_sent( int $project_id ): bool {
		return false !== get_option( ProjectMeta::WELCOME_SENT_OPTION_PREFIX . $project_id, false );
	}

	public function resend_admin( int $project_id ): bool {
		$project = $this->projects->find_by_id( $project_id );
		if ( ! is_array( $project ) || ! ProjectEntitlement::is_project_usable( $project ) ) {
			return false;
		}

		if ( function_exists( 'delete_option' ) ) {
			delete_option( ProjectMeta::WELCOME_SENT_OPTION_PREFIX . $project_id );
		}

		return $this->handle_scheduled_send( $project_id );
	}

	private function welcome_already_sent( int $project_id ): bool {
		$existing = $this->deliveries->find_by_idempotency_key( 'welcome:' . $project_id );

		return is_array( $existing ) && DeliveryStatus::SENT === (string) ( $existing['status'] ?? '' );
	}
}
