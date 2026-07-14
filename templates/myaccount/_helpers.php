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

if ( ! function_exists( 'pks_oi_render_notices' ) ) {
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
}

if ( ! function_exists( 'pks_oi_render_section_nav' ) ) {
	/**
	 * @param array<string, string> $sections
	 * @param array<string, string> $urls
	 */
	function pks_oi_render_section_nav( string $current, array $sections, array $urls ): void {
		if ( class_exists( '\PrikOgStreg\OnlineInvitations\MyAccount\SidebarContext' )
			&& \PrikOgStreg\OnlineInvitations\MyAccount\SidebarContext::renders_section_nav_in_sidebar() ) {
			return;
		}

		pks_oi_render_section_nav_markup( $current, $sections, $urls );
	}
}

if ( ! function_exists( 'pks_oi_render_section_nav_markup' ) ) {
	/**
	 * @param array<string, string> $sections
	 * @param array<string, string> $urls
	 */
	function pks_oi_render_section_nav_markup( string $current, array $sections, array $urls ): void {
		$items = [];
		foreach ( $sections as $slug => $label ) {
			$items[] = [
				'slug'      => $slug,
				'label'     => $label,
				'url'       => $urls[ $slug ] ?? '#',
				'status'    => 'neutral',
				'meta'      => '',
				'is_active' => $slug === $current,
			];
		}

		pks_oi_render_section_nav_groups(
			[
				[
					'slug'  => 'all',
					'label' => '',
					'items' => $items,
				],
			]
		);
	}
}

if ( ! function_exists( 'pks_oi_render_section_nav_groups' ) ) {
	/**
	 * @param list<array{slug:string,label:string,items:list<array<string,mixed>>}> $groups
	 */
	function pks_oi_render_section_nav_groups( array $groups ): void {
		if ( [] === $groups ) {
			return;
		}

		echo '<nav class="pks-oi-section-nav" aria-label="' . esc_attr__( 'Project sections', 'prikogstreg-online-invitations' ) . '">';

		foreach ( $groups as $group ) {
			$group_label = trim( (string) ( $group['label'] ?? '' ) );
			$items       = is_array( $group['items'] ?? null ) ? $group['items'] : [];

			if ( [] === $items ) {
				continue;
			}

			echo '<div class="pks-oi-section-nav__group">';
			if ( '' !== $group_label ) {
				printf(
					'<p class="pks-oi-section-nav__group-label">%s</p>',
					esc_html( $group_label )
				);
			}

			echo '<ul class="pks-oi-section-nav__list">';

			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				$slug      = (string) ( $item['slug'] ?? '' );
				$label     = (string) ( $item['label'] ?? '' );
				$url       = (string) ( $item['url'] ?? '#' );
				$status    = (string) ( $item['status'] ?? 'neutral' );
				$meta      = trim( (string) ( $item['meta'] ?? '' ) );
				$is_active = ! empty( $item['is_active'] );

				$item_classes = [ 'pks-oi-section-nav__item' ];
				if ( $is_active ) {
					$item_classes[] = 'is-active';
				}
				if ( '' !== $status ) {
					$item_classes[] = 'is-' . sanitize_html_class( $status );
				}

				$status_label = pks_oi_section_status_label( $status );

				echo '<li class="' . esc_attr( implode( ' ', $item_classes ) ) . '">';
				printf(
					'<a class="pks-oi-section-nav__link" href="%1$s"%2$s>',
					esc_url( $url ),
					$is_active ? ' aria-current="page"' : ''
				);
				printf(
					'<span class="pks-oi-section-nav__status is-%1$s" aria-hidden="true"></span>',
					esc_attr( sanitize_html_class( $status ) )
				);
				if ( '' !== $status_label ) {
					printf(
						'<span class="pks-oi-sr-only">%s</span>',
						esc_html( $status_label )
					);
				}
				echo '<span class="pks-oi-section-nav__text">';
				printf( '<span class="pks-oi-section-nav__label">%s</span>', esc_html( $label ) );
				if ( '' !== $meta ) {
					printf( '<span class="pks-oi-section-nav__meta">%s</span>', esc_html( $meta ) );
				}
				echo '</span></a></li>';
			}

			echo '</ul></div>';
		}

		echo '</nav>';
	}
}

if ( ! function_exists( 'pks_oi_section_status_label' ) ) {
	function pks_oi_section_status_label( string $status ): string {
		return match ( $status ) {
			'complete'   => __( 'Complete', 'prikogstreg-online-invitations' ),
			'pending'    => __( 'Pending', 'prikogstreg-online-invitations' ),
			'attention'  => __( 'Needs attention', 'prikogstreg-online-invitations' ),
			'optional'   => __( 'Optional', 'prikogstreg-online-invitations' ),
			default      => '',
		};
	}
}
