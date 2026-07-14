<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin;

use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;

/**
 * Admin list filters for purchased invitation projects.
 */
final class ProjectAdminFilter {

	public const ALL         = 'all';
	public const ACTIVE      = 'active';
	public const DEACTIVATED = 'deactivated';

	/**
	 * @return list<string>
	 */
	public static function all(): array {
		return [
			self::ALL,
			self::ACTIVE,
			self::DEACTIVATED,
		];
	}

	public static function sanitize( string $filter ): string {
		$filter = sanitize_key( $filter );

		return in_array( $filter, self::all(), true ) ? $filter : self::ALL;
	}

	public static function label( string $filter ): string {
		return match ( self::sanitize( $filter ) ) {
			self::ACTIVE      => __( 'Active', 'prikogstreg-online-invitations' ),
			self::DEACTIVATED => __( 'Deactivated', 'prikogstreg-online-invitations' ),
			default           => __( 'All', 'prikogstreg-online-invitations' ),
		};
	}

	/**
	 * @return list<string>
	 */
	public static function deactivated_statuses(): array {
		return [
			ProjectStatus::DRAFT,
			ProjectStatus::RESTRICTED,
			ProjectStatus::EXPIRED,
			ProjectStatus::ARCHIVED,
		];
	}
}
