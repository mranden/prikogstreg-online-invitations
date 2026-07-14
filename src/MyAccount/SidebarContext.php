<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\MyAccount;

/**
 * Holds My Account sidebar navigation context for the current request.
 */
final class SidebarContext {

	/**
	 * @var array<string, mixed>|null
	 */
	private static ?array $nav = null;

	/**
	 * @param array<string, mixed> $nav
	 */
	public static function set( array $nav ): void {
		self::$nav = $nav;
	}

	public static function has_nav(): bool {
		return null !== self::$nav;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		return self::$nav ?? [];
	}

	public static function renders_section_nav_in_sidebar(): bool {
		if ( ! self::has_nav() ) {
			return false;
		}

		return function_exists( 'prikogstreg_my_account_should_render_sidebar' )
			&& prikogstreg_my_account_should_render_sidebar();
	}
}
