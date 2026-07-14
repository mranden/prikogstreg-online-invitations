<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

/**
 * Rewrite route for dedicated photo share landing pages.
 */
final class PhotoShareEndpoints {

	public const QUERY_VAR = 'pks_oi_photo_share_token';

	public const STREAM_QUERY_VAR = 'pks_oi_photo_stream_id';

	public const WALL_QUERY_VAR = 'pks_oi_photo_wall';

	public const REWRITE_VERSION_OPTION = 'pks_oi_photo_share_rewrite_version';

	public const REWRITE_VERSION = '2';

	public function register(): void {
		add_action( 'init', [ $this, 'register_rewrite_rules' ], 0 );
		add_filter( 'query_vars', [ $this, 'register_query_var' ] );
	}

	public function register_rewrite_rules(): void {
		add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([^&]+)' );
		add_rewrite_tag( '%' . self::STREAM_QUERY_VAR . '%', '([0-9]+)' );
		add_rewrite_tag( '%' . self::WALL_QUERY_VAR . '%', '([01])' );
		add_rewrite_rule(
			'^photos/([^/]+)/stream/([0-9]+)/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]&' . self::STREAM_QUERY_VAR . '=$matches[2]',
			'top'
		);
		add_rewrite_rule(
			'^photos/([^/]+)/wall/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]&' . self::WALL_QUERY_VAR . '=1',
			'top'
		);
		add_rewrite_rule(
			'^photos/([^/]+)/?$',
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
		$vars[] = self::STREAM_QUERY_VAR;
		$vars[] = self::WALL_QUERY_VAR;

		return $vars;
	}

	public static function stream_url( string $raw_token, int $photo_id ): string {
		if ( '' === $raw_token || $photo_id <= 0 || ! function_exists( 'home_url' ) ) {
			return '';
		}

		return home_url( '/photos/' . rawurlencode( $raw_token ) . '/stream/' . $photo_id . '/' );
	}

	public static function maybe_flush_rewrites(): void {
		$stored = (string) get_option( self::REWRITE_VERSION_OPTION, '' );
		if ( self::REWRITE_VERSION === $stored ) {
			return;
		}

		if ( ! did_action( 'init' ) ) {
			add_action(
				'init',
				static function (): void {
					self::maybe_flush_rewrites();
				},
				99
			);

			return;
		}

		( new self() )->register_rewrite_rules();

		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}

		update_option( self::REWRITE_VERSION_OPTION, self::REWRITE_VERSION, false );
	}
}
