<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\MyAccount;

use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;

/**
 * Theme-facing presentation data for My Account integration.
 *
 * Keeps project counts and optional nav badges available through WordPress
 * filters without requiring themes to query plugin tables directly.
 */
final class AccountPresentation {

	public function __construct(
		private readonly ProjectRepository $projects
	) {
	}

	public function register(): void {
		add_filter( 'pks_oi_user_project_count', [ $this, 'filter_user_project_count' ], 10, 2 );
	}

	/**
	 * Supply the active project count for a customer account.
	 *
	 * @param int $count   Count from earlier filters; ignored when this callback runs as the default provider.
	 * @param int $user_id WordPress user ID.
	 */
	public function filter_user_project_count( int $count, int $user_id ): int {
		if ( $user_id <= 0 ) {
			return 0;
		}

		return $this->projects->count_active_for_user( $user_id );
	}
}

/**
 * Return the active invitation project count for a user when the plugin is loaded.
 *
 * @param int $user_id WordPress user ID. Defaults to the current user.
 */
function pks_oi_get_user_project_count( int $user_id = 0 ): int {
	$user_id = $user_id > 0 ? $user_id : (int) get_current_user_id();

	/**
	 * Filter the number of active invitation projects owned by a customer.
	 *
	 * Registered by the Online Invitations plugin on My Account bootstrap.
	 *
	 * @param int $count   Default count before plugin resolution.
	 * @param int $user_id WordPress user ID.
	 */
	return (int) apply_filters( 'pks_oi_user_project_count', 0, $user_id );
}
