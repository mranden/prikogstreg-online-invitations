<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Scheduling;

use PrikOgStreg\OnlineInvitations\Database\Repositories\DeliveryRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\EventRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\SchedulerMeta;
use PrikOgStreg\OnlineInvitations\Privacy\RetentionPolicy;
use PrikOgStreg\OnlineInvitations\Storage\StorageCleanup;
use PrikOgStreg\OnlineInvitations\Storage\StorageLimits;

/**
 * Retention and cleanup jobs separate from project expiration.
 */
final class RetentionScheduler {

	private static bool $running = false;

	public function __construct(
		private ProjectRepository $projects,
		private DeliveryRepository $deliveries,
		private EventRepository $events,
		private StorageCleanup $storage_cleanup
	) {
		add_action( SchedulerMeta::CLEANUP_TEMP, [ $this, 'cleanup_temp_files' ] );
		add_action( SchedulerMeta::PRUNE_EVENT_LOGS, [ $this, 'prune_event_logs' ] );
		add_action( SchedulerMeta::PRUNE_DELIVERY_LOGS, [ $this, 'prune_delivery_logs' ] );

		ActionSchedulerBridge::register_sync_handler( SchedulerMeta::CLEANUP_TEMP, [ $this, 'cleanup_temp_files' ] );
		ActionSchedulerBridge::register_sync_handler( SchedulerMeta::PRUNE_EVENT_LOGS, [ $this, 'prune_event_logs' ] );
		ActionSchedulerBridge::register_sync_handler( SchedulerMeta::PRUNE_DELIVERY_LOGS, [ $this, 'prune_delivery_logs' ] );
	}

	public function register(): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		$this->schedule_if_missing( SchedulerMeta::CLEANUP_TEMP, [], time() + 300, 'cleanup:temp:' . gmdate( 'Y-m-d' ) );
		$this->schedule_if_missing( SchedulerMeta::PRUNE_EVENT_LOGS, [], time() + 600, 'prune:events:' . gmdate( 'Y-W' ) );
		$this->schedule_if_missing( SchedulerMeta::PRUNE_DELIVERY_LOGS, [], time() + 900, 'prune:deliveries:' . gmdate( 'Y-m' ) );
	}

	public function cleanup_temp_files(): void {
		if ( self::$running ) {
			return;
		}

		self::$running = true;

		try {
			$removed = 0;
			foreach ( $this->projects->list_storage_uuids() as $storage_uuid ) {
				$removed += $this->storage_cleanup->cleanup_abandoned_temp_files(
					$storage_uuid,
					StorageLimits::TEMP_MAX_AGE_SECONDS
				);
			}

			if ( function_exists( 'as_schedule_single_action' ) ) {
				ActionSchedulerBridge::schedule_single(
					SchedulerMeta::CLEANUP_TEMP,
					[],
					time() + DAY_IN_SECONDS,
					'cleanup:temp:' . gmdate( 'Y-m-d', time() + DAY_IN_SECONDS )
				);
			}

			do_action( 'pks_oi_cleanup_temp_completed', $removed );
		} finally {
			self::$running = false;
		}
	}

	public function prune_event_logs(): void {
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-' . RetentionPolicy::EVENT_LOG_MONTHS . ' months' ) );
		$this->events->delete_older_than( $cutoff );

		if ( function_exists( 'as_schedule_single_action' ) ) {
			ActionSchedulerBridge::schedule_single(
				SchedulerMeta::PRUNE_EVENT_LOGS,
				[],
				time() + ( 7 * DAY_IN_SECONDS ),
				'prune:events:' . gmdate( 'Y-W', time() + ( 7 * DAY_IN_SECONDS ) )
			);
		}
	}

	public function prune_delivery_logs(): void {
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-' . RetentionPolicy::DELIVERY_LOG_MONTHS . ' months' ) );
		$this->deliveries->anonymize_older_than( $cutoff );

		if ( function_exists( 'as_schedule_single_action' ) ) {
			ActionSchedulerBridge::schedule_single(
				SchedulerMeta::PRUNE_DELIVERY_LOGS,
				[],
				time() + ( 28 * DAY_IN_SECONDS ),
				'prune:deliveries:' . gmdate( 'Y-m', time() + ( 28 * DAY_IN_SECONDS ) )
			);
		}
	}

	private function schedule_if_missing( string $hook, array $args, int $timestamp, string $unique_key ): void {
		if ( ActionSchedulerBridge::is_scheduled( $hook, $args, $unique_key ) ) {
			return;
		}

		ActionSchedulerBridge::schedule_single( $hook, $args, $timestamp, $unique_key );
	}
}
