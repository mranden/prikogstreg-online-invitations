<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

/**
 * Public rewrite route for opaque invitation tokens.
 */
final class Endpoints {

	public const QUERY_VAR = 'pks_oi_invitation_token';

	public const REWRITE_VERSION_OPTION = 'pks_oi_public_rewrite_version';

	public const REWRITE_VERSION = '1';

	public function register(): void {
		add_action( 'init', [ $this, 'register_rewrite_rules' ], 0 );
		add_filter( 'query_vars', [ $this, 'register_query_var' ] );
	}

	public function register_rewrite_rules(): void {
		add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([^&]+)' );
		add_rewrite_rule(
			'^invitation/([^/]+)/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * @param list<string> $vars
	 * @return list<string>
	 */
	public function register_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	public static function maybe_flush_rewrites(): void {
		$stored = (string) get_option( self::REWRITE_VERSION_OPTION, '' );
		if ( self::REWRITE_VERSION === $stored ) {
			return;
		}

		( new self() )->register_rewrite_rules();

		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}

		update_option( self::REWRITE_VERSION_OPTION, self::REWRITE_VERSION, false );
	}
}
