<?php
/**
 * Shared My Account layout helpers.
 *
 * @package PrikOgStreg\OnlineInvitations
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param list<array{type:string,message:string}> $notices
 */
function pks_oi_render_notices( array $notices ): void {
	foreach ( $notices as $notice ) {
		$type = in_array( $notice['type'] ?? '', [ 'error', 'warning', 'success' ], true ) ? $notice['type'] : 'info';
		printf(
			'<div class="woocommerce-message woocommerce-message--%1$s pks-oi-notice" role="status">%2$s</div>',
			esc_attr( $type ),
			esc_html( (string) ( $notice['message'] ?? '' ) )
		);
	}
}

/**
 * @param array<string, string> $sections
 * @param array<string, string> $urls
 */
function pks_oi_render_section_nav( string $current, array $sections, array $urls ): void {
	echo '<nav class="pks-oi-section-nav" aria-label="' . esc_attr__( 'Project sections', 'prikogstreg-online-invitations' ) . '"><ul>';

	foreach ( $sections as $slug => $label ) {
		$active = $slug === $current ? ' aria-current="page"' : '';
		$url    = $urls[ $slug ] ?? '#';
		printf(
			'<li class="pks-oi-section-nav__item"><a href="%1$s"%2$s>%3$s</a></li>',
			esc_url( $url ),
			$active,
			esc_html( $label )
		);
	}

	echo '</ul></nav>';
}
