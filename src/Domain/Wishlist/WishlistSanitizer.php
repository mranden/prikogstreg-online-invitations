<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Wishlist;

/**
 * Sanitizes wishlist text and external URLs.
 */
final class WishlistSanitizer {

	public const MAX_TITLE_LENGTH = 255;

	public const MAX_DESCRIPTION_LENGTH = 5000;

	public const MAX_QUANTITY = 99;

	public static function title( string $value ): string {
		$value = wp_strip_all_tags( trim( $value ) );

		return mb_substr( $value, 0, self::MAX_TITLE_LENGTH );
	}

	public static function description( string $value ): string {
		$value = wp_strip_all_tags( trim( $value ) );

		return mb_substr( $value, 0, self::MAX_DESCRIPTION_LENGTH );
	}

	/**
	 * @return string|null Safe http(s) URL or null when empty/invalid.
	 */
	public static function external_url( string $value ): ?string {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}

		$url = esc_url_raw( $value, [ 'http', 'https' ] );
		if ( '' === $url || ! self::is_safe_http_url( $url ) ) {
			return null;
		}

		return $url;
	}

	/**
	 * @return string|null
	 */
	public static function image_url( string $value ): ?string {
		return self::external_url( $value );
	}

	public static function quantity( mixed $value ): int {
		$quantity = (int) $value;

		return max( 1, min( self::MAX_QUANTITY, $quantity ) );
	}

	public static function sort_order( mixed $value ): int {
		return max( 0, (int) $value );
	}

	public static function status( string $value ): string {
		$value = sanitize_key( $value );

		return in_array( $value, [ WishlistItemStatus::ACTIVE, WishlistItemStatus::HIDDEN ], true )
			? $value
			: WishlistItemStatus::ACTIVE;
	}

	public static function display_name( string $value ): string {
		$value = wp_strip_all_tags( trim( $value ) );

		return mb_substr( $value, 0, 120 );
	}

	private static function is_safe_http_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return false;
		}

		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			return false;
		}

		$host = (string) ( $parts['host'] ?? '' );

		return '' !== $host;
	}
}
