<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin;

/**
 * Custom capabilities and role mapping.
 */
final class Capabilities {

	/** Customer-owned project access (My Account). */
	public const MANAGE_OWN = 'pks_oi_manage_own_projects';

	/** Support staff lifecycle and diagnostics (legacy alias for edit/tools). */
	public const SUPPORT = 'pks_oi_support_projects';

	/** Top-level plugin administration. */
	public const MANAGE = 'manage_online_invitations';

	/** View invitation projects in wp-admin. */
	public const VIEW = 'view_online_invitation_projects';

	/** Safe support edits on invitation projects. */
	public const EDIT = 'edit_online_invitation_projects';

	/** Photo moderation across projects. */
	public const MODERATE_PHOTOS = 'moderate_online_invitation_photos';

	/** Plugin settings screen. */
	public const MANAGE_SETTINGS = 'manage_online_invitation_settings';

	/** Destructive repair/tools actions. */
	public const RUN_TOOLS = 'run_online_invitation_tools';

	/**
	 * @deprecated Use Capabilities::VIEW — kept for backward compatibility.
	 */
	public const ADMIN_MENU = self::VIEW;

	/**
	 * @return list<string>
	 */
	public static function all(): array {
		return [
			self::MANAGE_OWN,
			self::SUPPORT,
			self::MANAGE,
			self::VIEW,
			self::EDIT,
			self::MODERATE_PHOTOS,
			self::MANAGE_SETTINGS,
			self::RUN_TOOLS,
		];
	}

	/**
	 * @return list<string>
	 */
	public static function admin_caps(): array {
		return [
			self::MANAGE,
			self::VIEW,
			self::EDIT,
			self::MODERATE_PHOTOS,
			self::MANAGE_SETTINGS,
			self::RUN_TOOLS,
			self::SUPPORT,
		];
	}

	public static function register_for_roles(): void {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::all() as $cap ) {
				$admin->add_cap( $cap );
			}
		}

		$shop_manager = get_role( 'shop_manager' );
		if ( $shop_manager ) {
			$shop_manager->add_cap( self::SUPPORT );
			$shop_manager->add_cap( self::VIEW );
			$shop_manager->add_cap( self::EDIT );
			$shop_manager->add_cap( self::MODERATE_PHOTOS );
			$shop_manager->add_cap( self::RUN_TOOLS );
		}

		$customer = get_role( 'customer' );
		if ( $customer ) {
			$customer->add_cap( self::MANAGE_OWN );
		}
	}
}
