<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Support;

/**
 * Normalizes adapter preview HTML for the My Account poster viewport canvas.
 */
final class PosterPreviewHtml {

	public static function prepare_for_viewport( string $html ): string {
		$html = trim( $html );
		if ( '' === $html ) {
			return '';
		}

		if ( ! preg_match( '/\bbpp-public-invitation\b/i', $html ) ) {
			return self::ensure_public_page_wrapper( $html );
		}

		$previous = libxml_use_internal_errors( true );
		$document = new \DOMDocument();

		$loaded = @$document->loadHTML(
			'<?xml encoding="utf-8" ?>' . $html,
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return self::ensure_public_page_wrapper( $html );
		}

		$root = $document->documentElement;
		if ( ! $root instanceof \DOMElement ) {
			return self::ensure_public_page_wrapper( $html );
		}

		if ( ! self::element_has_class( $root, 'bpp-public-invitation' ) ) {
			return self::ensure_public_page_wrapper( $html );
		}

		$pages = self::collect_public_pages( $root );
		if ( [] === $pages ) {
			return self::ensure_public_page_wrapper( $html );
		}

		return implode(
			'',
			array_map(
				static fn( string $page ): string => '<div class="pks-oi-poster-page">' . $page . '</div>',
				$pages
			)
		);
	}

	private static function ensure_public_page_wrapper( string $html ): string {
		if ( preg_match( '/\bbpp-public-page\b/i', $html ) ) {
			return '<div class="pks-oi-poster-page">' . $html . '</div>';
		}

		return '<div class="pks-oi-poster-page"><div class="bpp-public-page" data-page="0">' . $html . '</div></div>';
	}

	/**
	 * @return list<string>
	 */
	private static function collect_public_pages( \DOMElement $invitation_root ): array {
		$pages = [];

		foreach ( $invitation_root->childNodes as $child ) {
			if ( ! $child instanceof \DOMElement ) {
				continue;
			}

			if ( self::element_has_class( $child, 'bpp-public-page' ) ) {
				$pages[] = self::element_html( $child );

				continue;
			}

			$nested = self::collect_public_pages( $child );
			if ( [] !== $nested ) {
				$pages = array_merge( $pages, $nested );
			}
		}

		return $pages;
	}

	private static function element_has_class( \DOMElement $element, string $class_name ): bool {
		return preg_match( '/\b' . preg_quote( $class_name, '/' ) . '\b/i', $element->getAttribute( 'class' ) ) === 1;
	}

	private static function element_html( \DOMElement $element ): string {
		$html = $element->ownerDocument?->saveHTML( $element );

		return is_string( $html ) ? $html : '';
	}
}
