<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

/**
 * Loads Prik og Streg theme fonts on isolated public pages.
 */
final class PublicThemeFonts {

	public static function enqueue(): void {
		if ( ! function_exists( 'get_template_directory_uri' ) ) {
			return;
		}

		$fonts_url = get_template_directory_uri() . '/assets/fonts/fonts.css';
		if ( '' === $fonts_url ) {
			return;
		}

		wp_enqueue_style(
			'pks-oi-theme-fonts',
			$fonts_url,
			[],
			null
		);
	}
}
