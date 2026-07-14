<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\MyAccount;

/**
 * WooCommerce My Account endpoint registration and menu integration.
 */
final class Endpoints {

	public const SLUG = 'online-invitations';

	public const REWRITE_VERSION_OPTION = 'pks_oi_myaccount_rewrite_version';

	public const REWRITE_VERSION = '1';

	public function register(): void {
		add_action( 'init', [ $this, 'register_rewrite_endpoint' ], 0 );
		add_filter( 'woocommerce_account_menu_items', [ $this, 'add_menu_item' ] );
		add_filter( 'woocommerce_get_query_vars', [ $this, 'register_query_var' ] );
	}

	public function register_rewrite_endpoint(): void {
		add_rewrite_endpoint( self::SLUG, EP_ROOT | EP_PAGES );
	}

	/**
	 * @param array<string, string> $items
	 * @return array<string, string>
	 */
	public function add_menu_item( array $items ): array {
		$label = __( 'Online invitations', 'prikogstreg-online-invitations' );
		$new   = [];

		foreach ( $items as $endpoint => $item_label ) {
			$new[ $endpoint ] = $item_label;
			if ( 'orders' === $endpoint ) {
				$new[ self::SLUG ] = $label;
			}
		}

		if ( ! isset( $new[ self::SLUG ] ) ) {
			$new[ self::SLUG ] = $label;
		}

		return $new;
	}

	/**
	 * @param array<string, string> $vars
	 * @return array<string, string>
	 */
	public function register_query_var( array $vars ): array {
		$vars[ self::SLUG ] = self::SLUG;

		return $vars;
	}

	public static function base_url(): string {
		if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
			return trailingslashit( (string) wc_get_account_endpoint_url( self::SLUG ) );
		}

		return '/my-account/' . self::SLUG . '/';
	}

	public static function project_url( int $project_id, string $section = '' ): string {
		$url = self::base_url() . $project_id . '/';
		if ( '' !== $section && ProjectSections::is_valid( $section ) && ProjectSections::OVERVIEW !== $section ) {
			$url .= trailingslashit( $section );
		}

		return $url;
	}

	public static function maybe_flush_rewrites(): void {
		$stored = (string) get_option( self::REWRITE_VERSION_OPTION, '' );
		if ( self::REWRITE_VERSION === $stored ) {
			return;
		}

		if ( function_exists( 'add_rewrite_endpoint' ) ) {
			( new self() )->register_rewrite_endpoint();
		}

		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}

		update_option( self::REWRITE_VERSION_OPTION, self::REWRITE_VERSION, false );
	}
}
