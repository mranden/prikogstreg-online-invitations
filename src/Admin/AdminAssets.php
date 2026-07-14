<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin;

use PrikOgStreg\OnlineInvitations\Admin\Invitations\InvitationAdminQuery;

/**
 * Enqueues admin CSS/JS for invitation support screens.
 */
final class AdminAssets {

	/** @var list<string> */
	private const SCREEN_HOOKS = [
		'toplevel_page_pks-online-invitations',
		'online-invitations_page_pks-online-invitations-photos',
		'online-invitations_page_pks-online-invitations-settings',
	];

	public function register(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function enqueue( string $hook ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_project_cpt = is_object( $screen ) && ProjectPostType::POST_TYPE === ( $screen->post_type ?? '' );
		$is_projects_admin = in_array( $hook, self::SCREEN_HOOKS, true );

		if ( ! $is_project_cpt && ! $is_projects_admin ) {
			return;
		}

		wp_enqueue_style(
			'pks-oi-admin',
			PKS_OI_PLUGIN_URL . 'assets/build/css/admin.css',
			[],
			PKS_OI_VERSION
		);

		wp_enqueue_script(
			'pks-oi-admin',
			PKS_OI_PLUGIN_URL . 'assets/build/js/admin.js',
			[],
			PKS_OI_VERSION,
			true
		);
	}
}
