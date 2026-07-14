<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Support;

/**
 * Normalizes builder page HTML stored with JSON/cart escaping artifacts.
 */
final class BuilderPageHtmlNormalizer {

	public static function normalize( string $html ): string {
		if ( class_exists( 'BPP\Integration\Order_Editor_View', false ) ) {
			return \BPP\Integration\Order_Editor_View::normalize_page_html( $html );
		}

		while ( str_contains( $html, '\\' ) ) {
			$unslashed = stripslashes( $html );
			if ( $unslashed === $html ) {
				break;
			}
			$html = $unslashed;
		}

		return preg_replace( '/-[0-9]+x[0-9]+\.(?=jpe?g|png|gif|webp)/i', '.', $html ) ?? $html;
	}
}
