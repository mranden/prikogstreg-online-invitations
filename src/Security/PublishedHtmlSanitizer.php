<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Security;

/**
 * Second-layer HTML safety check before writing published snapshots.
 */
final class PublishedHtmlSanitizer {

	public static function sanitize( string $html ): string {
		if ( self::contains_blocked_markup( $html ) ) {
			throw new \InvalidArgumentException( 'published_html_unsafe' );
		}

		return $html;
	}

	public static function contains_blocked_markup( string $html ): bool {
		$lower = strtolower( $html );

		return str_contains( $lower, '<script' )
			|| str_contains( $lower, 'javascript:' )
			|| preg_match( '/on[a-z]+\s*=/i', $html ) === 1;
	}
}
