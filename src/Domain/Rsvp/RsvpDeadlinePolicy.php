<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Rsvp;

/**
 * Determines whether RSVP submissions are accepted for a project.
 */
final class RsvpDeadlinePolicy {

	/**
	 * @param array<string, mixed> $project
	 */
	public static function is_open( array $project ): bool {
		return ! self::is_past_deadline( (string) ( $project['rsvp_deadline_utc'] ?? '' ) );
	}

	public static function is_past_deadline( string $deadline_utc ): bool {
		$deadline_utc = trim( $deadline_utc );
		if ( '' === $deadline_utc ) {
			return false;
		}

		$timestamp = strtotime( $deadline_utc . ' UTC' );

		return false !== $timestamp && $timestamp <= time();
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public static function deadline_label( array $project ): string {
		$deadline_utc = trim( (string) ( $project['rsvp_deadline_utc'] ?? '' ) );
		if ( '' === $deadline_utc ) {
			return '';
		}

		$timezone = (string) ( $project['timezone'] ?? 'Europe/Copenhagen' );

		try {
			$dt = new \DateTimeImmutable( $deadline_utc, new \DateTimeZone( 'UTC' ) );

			return $dt->setTimezone( new \DateTimeZone( $timezone ) )->format( 'Y-m-d H:i' );
		} catch ( \Exception $exception ) {
			return $deadline_utc;
		}
	}
}
