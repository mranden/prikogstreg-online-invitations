<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Scheduling;

use PrikOgStreg\OnlineInvitations\Domain\Delivery\SchedulerMeta;

/**
 * Schedules Action Scheduler jobs with idempotency keys and synchronous fallback.
 */
final class ActionSchedulerBridge {

	/** @var array<string, callable> */
	private static array $sync_handlers = [];

	public static function register_sync_handler( string $hook, callable $handler ): void {
		self::$sync_handlers[ $hook ] = $handler;
	}

	public static function schedule_single(
		string $hook,
		array $args,
		int $timestamp,
		string $unique_key
	): bool {
		if ( self::is_scheduled( $hook, $args, $unique_key ) ) {
			return false;
		}

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				$timestamp,
				$hook,
				$args,
				SchedulerMeta::GROUP,
				true,
				$unique_key
			);

			return true;
		}

		self::run_sync( $hook, $args );

		return true;
	}

	public static function unschedule( string $hook, array $args = [], ?string $unique_key = null ): void {
		if ( function_exists( 'as_unschedule_action' ) ) {
			if ( null !== $unique_key && function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( $hook, $args, SchedulerMeta::GROUP );
			} elseif ( function_exists( 'as_unschedule_action' ) ) {
				as_unschedule_action( $hook, $args, SchedulerMeta::GROUP );
			}
		}
	}

	public static function is_scheduled( string $hook, array $args, string $unique_key ): bool {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return false;
		}

		return false !== as_next_scheduled_action( $hook, $args, SchedulerMeta::GROUP );
	}

	/**
	 * @param array<int, mixed> $args
	 */
	public static function run_sync( string $hook, array $args ): void {
		$handler = self::$sync_handlers[ $hook ] ?? null;
		if ( ! is_callable( $handler ) ) {
			return;
		}

		$handler( ...$args );
	}
}
