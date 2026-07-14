<?php
/**
 * Uninstall handler — preserves customer data by default.
 *
 * @package PrikOgStreg\OnlineInvitations
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Customer data is retained unless an administrator explicitly defines
 * PKS_OI_UNINSTALL_DELETE_DATA as true before uninstall.
 */
if ( defined( 'PKS_OI_UNINSTALL_DELETE_DATA' ) && true === PKS_OI_UNINSTALL_DELETE_DATA ) {
	delete_option( 'pks_oi_db_version' );
	delete_option( 'pks_oi_version' );
	delete_option( 'pks_oi_activated_at' );
}
