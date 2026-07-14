<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

/**
 * Public rewrite route for opaque invitation tokens.
 */
final class Endpoints {

	public const QUERY_VAR = 'pks_oi_invitation_token';

	public const ENVELOPE_ASSET_QUERY_VAR = 'pks_oi_envelope_asset';

	public const POSTER_ASSET_QUERY_VAR = 'pks_oi_poster_asset';

	public const PRODUCT_SAMPLE_QUERY_VAR = 'pks_oi_product_sample_id';

	public const REWRITE_VERSION_OPTION = 'pks_oi_public_rewrite_version';

	public const REWRITE_VERSION = '4';

	public function register(): void {
		add_action( 'init', [ $this, 'register_rewrite_rules' ], 0 );
		add_filter( 'query_vars', [ $this, 'register_query_var' ] );
	}

	public function register_rewrite_rules(): void {
		add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([^&]+)' );
		add_rewrite_tag( '%' . self::ENVELOPE_ASSET_QUERY_VAR . '%', '([01])' );
		add_rewrite_tag( '%' . self::POSTER_ASSET_QUERY_VAR . '%', '(display|fonts)' );
		add_rewrite_rule(
			'^invitation/([^/]+)/envelope-image/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]&' . self::ENVELOPE_ASSET_QUERY_VAR . '=1',
			'top'
		);
		add_rewrite_rule(
			'^invitation/([^/]+)/poster-asset/(display|fonts)/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]&' . self::POSTER_ASSET_QUERY_VAR . '=$matches[2]',
			'top'
		);
		add_rewrite_rule(
			'^invitation/([^/]+)/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^invitation-sample/([0-9]+)/?$',
			'index.php?' . self::PRODUCT_SAMPLE_QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * @param list<string> $vars
	 * @return list<string>
	 */
	public function register_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		$vars[] = self::ENVELOPE_ASSET_QUERY_VAR;
		$vars[] = self::POSTER_ASSET_QUERY_VAR;
		$vars[] = self::PRODUCT_SAMPLE_QUERY_VAR;

		return $vars;
	}

	public static function product_sample_url( int $product_id ): string {
		if ( $product_id <= 0 || ! function_exists( 'home_url' ) ) {
			return '';
		}

		return home_url( '/invitation-sample/' . $product_id . '/' );
	}

	public static function envelope_image_url( string $raw_token ): string {
		if ( '' === $raw_token || ! function_exists( 'home_url' ) ) {
			return '';
		}

		return home_url( '/invitation/' . rawurlencode( $raw_token ) . '/envelope-image/' );
	}

	public static function poster_asset_url( string $raw_token, string $asset ): string {
		if ( '' === $raw_token || ! function_exists( 'home_url' ) ) {
			return '';
		}

		$asset = sanitize_key( $asset );
		if ( ! in_array( $asset, [ 'display', 'fonts' ], true ) ) {
			return '';
		}

		return home_url( '/invitation/' . rawurlencode( $raw_token ) . '/poster-asset/' . $asset . '/' );
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
