<?php
/**
 * Global theme integration helpers for Online Invitations.
 *
 * Loaded when My Account registers. Themes should guard calls with
 * `function_exists( 'pks_oi_my_account_is_available' )` when the plugin may be inactive.
 *
 * @package PrikOgStreg\OnlineInvitations
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the Online Invitations My Account integration is available.
 */
function pks_oi_my_account_is_available(): bool {
	return has_filter( 'pks_oi_user_project_count' );
}

/**
 * Return the active invitation project count for a user.
 *
 * @param int $user_id WordPress user ID. Defaults to the current user.
 */
function pks_oi_get_user_project_count( int $user_id = 0 ): int {
	if ( ! pks_oi_my_account_is_available() ) {
		return 0;
	}

	$user_id = $user_id > 0 ? $user_id : (int) get_current_user_id();

	/**
	 * Filter the number of active invitation projects owned by a customer.
	 *
	 * @param int $count   Default count before plugin resolution.
	 * @param int $user_id WordPress user ID.
	 */
	return (int) apply_filters( 'pks_oi_user_project_count', 0, $user_id );
}

/**
 * Base My Account URL for the invitation project list.
 */
function pks_oi_get_my_account_list_url(): string {
	if ( ! class_exists( '\PrikOgStreg\OnlineInvitations\MyAccount\Endpoints' ) ) {
		return '';
	}

	return \PrikOgStreg\OnlineInvitations\MyAccount\Endpoints::base_url();
}

/**
 * WooCommerce endpoint slug for Online Invitations.
 */
function pks_oi_get_my_account_endpoint_slug(): string {
	if ( ! class_exists( '\PrikOgStreg\OnlineInvitations\MyAccount\Endpoints' ) ) {
		return '';
	}

	return \PrikOgStreg\OnlineInvitations\MyAccount\Endpoints::SLUG;
}

/**
 * Summary data for theme links (header, dashboard, account cards).
 *
 * @param int $user_id WordPress user ID. Defaults to the current user.
 * @param int $limit   Maximum projects to include in the projects list (1–20).
 * @return array{
 *     count:int,
 *     list_url:string,
 *     primary_url:string,
 *     projects:list<array{
 *         project_id:int,
 *         title:string,
 *         url:string,
 *         status:string,
 *         publication_status:string,
 *         updated_at:string
 *     }>
 * }|null Null when the plugin integration is unavailable or the user is not logged in.
 */
function pks_oi_get_user_projects_nav( int $user_id = 0, int $limit = 5 ): ?array {
	if ( ! pks_oi_my_account_is_available() ) {
		return null;
	}

	$user_id = $user_id > 0 ? $user_id : (int) get_current_user_id();
	if ( $user_id <= 0 ) {
		return null;
	}

	$limit = max( 1, min( 20, $limit ) );

	/**
	 * Filter invitation project navigation data for theme presentation.
	 *
	 * @param array<string,mixed> $nav     Default empty structure.
	 * @param int                 $user_id WordPress user ID.
	 * @param int                 $limit   Maximum projects in the list.
	 */
	$nav = apply_filters( 'pks_oi_user_projects_nav', [], $user_id, $limit );

	return is_array( $nav ) ? $nav : null;
}
