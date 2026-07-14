<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

/**
 * Publication and lifecycle checks for anonymous public invitation access.
 */
final class PublicEntitlement {

	/**
	 * @param array<string, mixed> $project
	 */
	public static function is_publicly_available( array $project ): bool {
		if ( ProjectStatus::ACTIVE !== (string) ( $project['status'] ?? '' ) ) {
			return false;
		}

		if ( PublicationStatus::PUBLISHED !== (string) ( $project['publication_status'] ?? '' ) ) {
			return false;
		}

		if ( '' !== (string) ( $project['last_error_code'] ?? '' ) ) {
			return false;
		}

		if ( self::is_timestamp_set( $project['deleted_at_utc'] ?? null ) ) {
			return false;
		}

		if ( self::is_timestamp_set( $project['restricted_at_utc'] ?? null ) ) {
			return false;
		}

		if ( self::is_timestamp_set( $project['expired_at_utc'] ?? null ) ) {
			return false;
		}

		if ( self::is_past_expiry( (string) ( $project['expires_at_utc'] ?? '' ) ) ) {
			return false;
		}

		$manifest = trim( (string) ( $project['published_manifest_path'] ?? '' ) );

		return '' !== $manifest;
	}

	/**
	 * @param array<string, mixed> $guest
	 */
	public static function is_guest_accessible( array $guest ): bool {
		if ( self::is_timestamp_set( $guest['archived_at_utc'] ?? null ) ) {
			return false;
		}

		$status = (string) ( $guest['invitation_status'] ?? '' );

		return 'cancelled' !== $status;
	}

	private static function is_past_expiry( string $expires_at_utc ): bool {
		if ( '' === $expires_at_utc ) {
			return false;
		}

		$timestamp = strtotime( $expires_at_utc . ' UTC' );

		return false !== $timestamp && $timestamp <= time();
	}

	/**
	 * @param mixed $value
	 */
	private static function is_timestamp_set( $value ): bool {
		return null !== $value && '' !== (string) $value;
	}
}
