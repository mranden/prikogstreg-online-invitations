<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Support;

/**
 * Validates that published invitation HTML contains substantive visible content.
 */
final class PublishedHtmlValidator {

	public static function has_visible_content( string $html ): bool {
		$html = trim( $html );
		if ( '' === $html ) {
			return false;
		}

		if ( preg_match( '/<(img|picture|video|canvas)\b/i', $html ) ) {
			return true;
		}

		$stripped = wp_strip_all_tags( $html );
		$stripped = preg_replace( '/\s+/u', '', $stripped ) ?? '';

		return '' !== $stripped;
	}

	public static function is_empty_wrapper_only( string $html ): bool {
		if ( ! self::has_visible_content( $html ) ) {
			return true;
		}

		$trimmed = trim( $html );

		return (bool) preg_match(
			'/^<div[^>]*class="[^"]*bpp-public-invitation[^"]*"[^>]*>\s*<\/div>$/iu',
			$trimmed
		);
	}
}
