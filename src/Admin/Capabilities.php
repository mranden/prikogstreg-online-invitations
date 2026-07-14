<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin;

/**
 * Custom capabilities and role mapping.
 */
final class Capabilities {

	public const MANAGE_OWN   = 'pks_oi_manage_own_projects';
	public const SUPPORT      = 'pks_oi_support_projects';

	/**
	 * @return list<string>
	 */
	public static function all(): array {
		return [
			self::MANAGE_OWN,
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
		}

		$customer = get_role( 'customer' );
		if ( $customer ) {
			$customer->add_cap( self::MANAGE_OWN );
		}
	}
}
