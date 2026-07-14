<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

/**
 * Entitlement rules for project editing, preview, and publication.
 */
final class ProjectEntitlement {

	/**
	 * @return list<string>
	 */
	public static function qualifying_statuses(): array {
		return [
			'on-hold',
			'processing',
			'completed',
		];
	}

	public static function is_qualifying_status( string $status ): bool {
		$normalized = str_replace( 'wc-', '', $status );

		return in_array( $normalized, self::qualifying_statuses(), true );
	}

	public static function initial_project_status(): string {
		return ProjectStatus::ACTIVE;
	}

	public static function is_project_usable( array $project ): bool {
		if ( ProjectStatus::DELETED === ( $project['status'] ?? '' ) ) {
			return false;
		}

		if ( '' !== (string) ( $project['last_error_code'] ?? '' ) ) {
			return false;
		}

		return (int) ( $project['state_version'] ?? 0 ) >= 1;
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public static function can_edit_project( array $project ): bool {
		if ( ! self::is_project_usable( $project ) ) {
			return false;
		}

		$status = (string) ( $project['status'] ?? '' );

		return ! in_array( $status, [ ProjectStatus::RESTRICTED, ProjectStatus::EXPIRED, ProjectStatus::ARCHIVED, ProjectStatus::DELETED ], true );
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public static function has_required_event_data( array $project ): bool {
		$title = trim( (string) ( $project['event_title'] ?? '' ) );
		if ( '' === $title ) {
			return false;
		}

		return '' !== (string) ( $project['event_start_utc'] ?? '' ) || '' !== (string) ( $project['event_end_utc'] ?? '' );
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public static function can_publish_project( array $project ): bool {
		return self::can_edit_project( $project ) && self::has_required_event_data( $project );
	}
}
