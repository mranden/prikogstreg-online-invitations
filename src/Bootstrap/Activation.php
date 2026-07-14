<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Bootstrap;

use PrikOgStreg\OnlineInvitations\MyAccount\Endpoints;
use PrikOgStreg\OnlineInvitations\Public\Endpoints as PublicEndpoints;
use PrikOgStreg\OnlineInvitations\Admin\Capabilities;
use PrikOgStreg\OnlineInvitations\Admin\ProjectPostType;
use PrikOgStreg\OnlineInvitations\Database\Migrator;
use PrikOgStreg\OnlineInvitations\Storage\StorageBootstrap;
use PrikOgStreg\OnlineInvitations\Storage\StoragePath;

/**
 * Plugin activation — schema, capabilities, and options.
 */
final class Activation {

	public static function run(): void {
		Capabilities::register_for_roles();

		global $wpdb;

		$migrator = new Migrator( $wpdb );
		$migrator->install();

		( new StorageBootstrap( new StoragePath() ) )->ensure_fallback_protection();

		if ( get_option( 'pks_oi_version', false ) === false ) {
			add_option( 'pks_oi_version', PKS_OI_VERSION, '', false );
		}

		if ( get_option( 'pks_oi_activated_at', false ) === false ) {
			add_option( 'pks_oi_activated_at', gmdate( 'c' ), '', false );
		}

		update_option( 'pks_oi_version', PKS_OI_VERSION );

		Endpoints::maybe_flush_rewrites();
		PublicEndpoints::maybe_flush_rewrites();

		if ( ! post_type_exists( ProjectPostType::POST_TYPE ) ) {
			( new ProjectPostType() )->register_post_type();
		}
	}
}
