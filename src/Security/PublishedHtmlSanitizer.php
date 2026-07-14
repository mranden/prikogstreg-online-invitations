<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Security;

/**
 * Second-layer HTML safety check before writing and serving published snapshots.
 */
final class PublishedHtmlSanitizer {

	/** @var list<string> */
	private const FORBIDDEN_TAGS = [
		'script',
		'iframe',
		'object',
		'embed',
		'form',
		'input',
		'button',
		'link',
		'meta',
		'base',
		'svg',
	];

	public static function sanitize( string $html ): string {
		if ( self::contains_blocked_markup( $html ) ) {
			throw new \InvalidArgumentException( 'published_html_unsafe' );
		}

		return $html;
	}

	public static function contains_blocked_markup( string $html ): bool {
		$lower = strtolower( $html );

		foreach ( self::FORBIDDEN_TAGS as $tag ) {
			if ( str_contains( $lower, '<' . $tag ) ) {
				return true;
			}
		}

		if ( str_contains( $lower, 'javascript:' ) || str_contains( $lower, 'vbscript:' ) ) {
			return true;
		}

		if ( preg_match( '/on[a-z]+\s*=/i', $html ) === 1 ) {
			return true;
		}

		if ( preg_match( '/expression\s*\(/i', $html ) === 1 ) {
			return true;
		}

		if ( preg_match( '/@import\b/i', $html ) === 1 ) {
			return true;
		}

		if ( preg_match( '/data:text\/html/i', $html ) === 1 ) {
			return true;
		}

		if ( preg_match( '/-moz-binding/i', $html ) === 1 ) {
			return true;
		}

		if ( preg_match( '/behavior\s*:/i', $html ) === 1 ) {
			return true;
		}

		return false;
	}
}
