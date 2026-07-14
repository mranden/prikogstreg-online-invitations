<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

/**
 * Calculates project expiry from event dates and admin overrides.
 */
final class ProjectExpiration {

	public const DAYS_AFTER_EVENT = 90;

	public static function calculate_initial_expiry( ?string $event_end_utc, ?string $event_start_utc ): ?string {
		return self::from_event_dates( $event_end_utc, $event_start_utc );
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public static function effective_expiry( array $project ): ?string {
		$override = trim( (string) ( $project['expiry_override_utc'] ?? '' ) );
		if ( '' !== $override ) {
			return $override;
		}

		$stored = trim( (string) ( $project['expires_at_utc'] ?? '' ) );
		if ( '' !== $stored ) {
			return $stored;
		}

		return self::from_event_dates(
			isset( $project['event_end_utc'] ) ? (string) $project['event_end_utc'] : null,
			isset( $project['event_start_utc'] ) ? (string) $project['event_start_utc'] : null
		);
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public static function should_schedule( array $project ): bool {
		if ( ProjectStatus::ACTIVE !== (string) ( $project['status'] ?? '' ) ) {
			return false;
		}

		return null !== self::effective_expiry( $project );
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public static function is_past_effective_expiry( array $project ): bool {
		$expiry = self::effective_expiry( $project );
		if ( null === $expiry || '' === $expiry ) {
			return false;
		}

		$timestamp = strtotime( $expiry . ' UTC' );

		return false !== $timestamp && $timestamp <= time();
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public static function recalculate_stored_expiry( array $project ): ?string {
		$override = trim( (string) ( $project['expiry_override_utc'] ?? '' ) );
		if ( '' !== $override ) {
			return $override;
		}

		return self::from_event_dates(
			isset( $project['event_end_utc'] ) ? (string) $project['event_end_utc'] : null,
			isset( $project['event_start_utc'] ) ? (string) $project['event_start_utc'] : null
		);
	}

	private static function from_event_dates( ?string $event_end_utc, ?string $event_start_utc ): ?string {
		$anchor = $event_end_utc ?: $event_start_utc;
		if ( null === $anchor || '' === $anchor ) {
			return null;
		}

		$timestamp = strtotime( $anchor . ' UTC' );
		if ( false === $timestamp ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp + ( self::DAYS_AFTER_EVENT * 86400 ) );
	}
}
