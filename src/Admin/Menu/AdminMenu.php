<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin\Menu;

use PrikOgStreg\OnlineInvitations\Admin\Capabilities;
use PrikOgStreg\OnlineInvitations\Admin\Invitations\InvitationsPage;
use PrikOgStreg\OnlineInvitations\Admin\Photos\PhotosAdminPage;
use PrikOgStreg\OnlineInvitations\Admin\ProjectAdminListViewModel;
use PrikOgStreg\OnlineInvitations\Admin\Settings\SettingsPage;

/**
 * Top-level Online Invitations admin menu.
 */
final class AdminMenu {

	public function __construct(
		private InvitationsPage $invitations,
		private PhotosAdminPage $photos,
		private SettingsPage $settings
	) {}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_init', [ $this, 'maybe_redirect_legacy_slug' ] );
	}

	public function register_menu(): void {
		$slug = ProjectAdminListViewModel::PAGE_SLUG;

		add_menu_page(
			__( 'Online Invitations', 'prikogstreg-online-invitations' ),
			__( 'Online Invitations', 'prikogstreg-online-invitations' ),
			Capabilities::VIEW,
			$slug,
			[ $this->invitations, 'render' ],
			'dashicons-email-alt2',
			56
		);

		add_submenu_page(
			$slug,
			__( 'Invitations', 'prikogstreg-online-invitations' ),
			__( 'Invitations', 'prikogstreg-online-invitations' ),
			Capabilities::VIEW,
			$slug,
			[ $this->invitations, 'render' ]
		);

		add_submenu_page(
			$slug,
			__( 'Photos', 'prikogstreg-online-invitations' ),
			__( 'Photos', 'prikogstreg-online-invitations' ),
			Capabilities::MODERATE_PHOTOS,
			$slug . '-photos',
			[ $this->photos, 'render' ]
		);

		add_submenu_page(
			$slug,
			__( 'Settings', 'prikogstreg-online-invitations' ),
			__( 'Settings', 'prikogstreg-online-invitations' ),
			Capabilities::MANAGE_SETTINGS,
			$slug . '-settings',
			[ $this->settings, 'render' ]
		);
	}

	public function maybe_redirect_legacy_slug(): void {
		if ( ! is_admin() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( (string) $_GET['page'] ) : '';
		if ( ProjectAdminListViewModel::LEGACY_PAGE_SLUG !== $page ) {
			return;
		}

		$args = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $args['page'] );
		$args['page'] = ProjectAdminListViewModel::PAGE_SLUG;

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
