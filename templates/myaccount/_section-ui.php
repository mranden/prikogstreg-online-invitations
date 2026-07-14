<?php
/**
 * Shared My Account section UI helpers.
 *
 * @package PrikOgStreg\OnlineInvitations
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pks_oi_sidebar_active' ) ) {
	function pks_oi_sidebar_active(): bool {
		return class_exists( '\PrikOgStreg\OnlineInvitations\MyAccount\SidebarContext' )
			&& \PrikOgStreg\OnlineInvitations\MyAccount\SidebarContext::renders_section_nav_in_sidebar();
	}
}

if ( ! function_exists( 'pks_oi_project_open' ) ) {
	function pks_oi_project_open( string $modifier = '' ): void {
		$classes = trim( 'pks-oi pks-oi-myaccount pks-oi-project ' . $modifier );
		printf( '<div class="%s">', esc_attr( $classes ) );
	}
}

if ( ! function_exists( 'pks_oi_project_close' ) ) {
	function pks_oi_project_close(): void {
		echo '</div>';
	}
}

if ( ! function_exists( 'pks_oi_section_open' ) ) {
	function pks_oi_section_open( string $id, string $title, string $intro = '', string $modifier = '' ): void {
		$classes = trim( 'pks-oi-section ' . $modifier );
		printf( '<section class="%s" aria-labelledby="%s">', esc_attr( $classes ), esc_attr( $id ) );
		echo '<header class="pks-oi-section__header">';
		printf( '<h3 class="pks-oi-section__title" id="%s">%s</h3>', esc_attr( $id ), esc_html( $title ) );
		if ( '' !== $intro ) {
			printf( '<p class="pks-oi-section__intro">%s</p>', esc_html( $intro ) );
		}
		echo '</header><div class="pks-oi-section__body">';
	}
}

if ( ! function_exists( 'pks_oi_section_close' ) ) {
	function pks_oi_section_close(): void {
		echo '</div></section>';
	}
}

if ( ! function_exists( 'pks_oi_render_badge' ) ) {
	function pks_oi_render_badge( string $label, string $variant = 'neutral' ): void {
		printf(
			'<span class="pks-oi-status-badge pks-oi-status-badge--%1$s">%2$s</span>',
			esc_attr( sanitize_html_class( $variant ) ),
			esc_html( $label )
		);
	}
}

if ( ! function_exists( 'pks_oi_project_status_badge' ) ) {
	function pks_oi_project_status_badge( string $status ): void {
		$map = [
			'active'     => [ __( 'Active', 'prikogstreg-online-invitations' ), 'success' ],
			'draft'      => [ __( 'Draft', 'prikogstreg-online-invitations' ), 'warning' ],
			'restricted' => [ __( 'Restricted', 'prikogstreg-online-invitations' ), 'warning' ],
			'expired'    => [ __( 'Expired', 'prikogstreg-online-invitations' ), 'warning' ],
			'archived'   => [ __( 'Archived', 'prikogstreg-online-invitations' ), 'neutral' ],
		];
		$entry = $map[ $status ] ?? [ ucfirst( $status ), 'neutral' ];
		pks_oi_render_badge( (string) $entry[0], (string) $entry[1] );
	}
}

if ( ! function_exists( 'pks_oi_publication_badge' ) ) {
	function pks_oi_publication_badge( string $status ): void {
		if ( 'published' === $status ) {
			pks_oi_render_badge( __( 'Published', 'prikogstreg-online-invitations' ), 'success' );
			return;
		}
		pks_oi_render_badge( __( 'Not published', 'prikogstreg-online-invitations' ), 'neutral' );
	}
}

if ( ! function_exists( 'pks_oi_rsvp_badge' ) ) {
	function pks_oi_rsvp_badge( string $status ): void {
		$map = [
			'attending' => [ __( 'Attending', 'prikogstreg-online-invitations' ), 'success' ],
			'declined'  => [ __( 'Not attending', 'prikogstreg-online-invitations' ), 'warning' ],
			'maybe'     => [ __( 'Maybe', 'prikogstreg-online-invitations' ), 'neutral' ],
			'pending'   => [ __( 'Pending', 'prikogstreg-online-invitations' ), 'neutral' ],
		];
		$entry = $map[ $status ] ?? [ ucfirst( $status ), 'neutral' ];
		pks_oi_render_badge( (string) $entry[0], (string) $entry[1] );
	}
}

if ( ! function_exists( 'pks_oi_invitation_badge' ) ) {
	function pks_oi_invitation_badge( string $status ): void {
		$map = [
			'sent'     => [ __( 'Sent', 'prikogstreg-online-invitations' ), 'success' ],
			'opened'   => [ __( 'Opened', 'prikogstreg-online-invitations' ), 'success' ],
			'bounced'  => [ __( 'Bounced', 'prikogstreg-online-invitations' ), 'danger' ],
			'not_sent' => [ __( 'Not sent', 'prikogstreg-online-invitations' ), 'neutral' ],
		];
		$entry = $map[ $status ] ?? [ ucfirst( str_replace( '_', ' ', $status ) ), 'neutral' ];
		pks_oi_render_badge( (string) $entry[0], (string) $entry[1] );
	}
}

if ( ! function_exists( 'pks_oi_render_stats' ) ) {
	/**
	 * @param list<array{label:string,value:string,url?:string}> $stats
	 */
	function pks_oi_render_stats( array $stats ): void {
		if ( [] === $stats ) {
			return;
		}
		echo '<dl class="pks-oi-stats">';
		foreach ( $stats as $stat ) {
			$label = (string) ( $stat['label'] ?? '' );
			$value = (string) ( $stat['value'] ?? '' );
			$url   = (string) ( $stat['url'] ?? '' );
			echo '<div class="pks-oi-stats__item">';
			printf( '<dt class="pks-oi-stats__label">%s</dt>', esc_html( $label ) );
			echo '<dd class="pks-oi-stats__value">';
			if ( '' !== $url ) {
				printf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $value ) );
			} else {
				echo esc_html( $value );
			}
			echo '</dd></div>';
		}
		echo '</dl>';
	}
}

if ( ! function_exists( 'pks_oi_render_empty_state' ) ) {
	/**
	 * @param array{label:string,url:string}|null $cta
	 */
	function pks_oi_render_empty_state( string $title, string $message, ?array $cta = null ): void {
		echo '<div class="pks-oi-empty-state">';
		printf( '<h4 class="pks-oi-empty-state__title">%s</h4>', esc_html( $title ) );
		printf( '<p class="pks-oi-empty-state__message">%s</p>', esc_html( $message ) );
		if ( is_array( $cta ) && '' !== (string) ( $cta['url'] ?? '' ) ) {
			printf(
				'<p class="pks-oi-empty-state__action"><a class="button button-primary" href="%1$s">%2$s</a></p>',
				esc_url( (string) $cta['url'] ),
				esc_html( (string) ( $cta['label'] ?? __( 'Get started', 'prikogstreg-online-invitations' ) ) )
			);
		}
		echo '</div>';
	}
}

if ( ! function_exists( 'pks_oi_render_field' ) ) {
	/**
	 * @param array<string, mixed> $args
	 */
	function pks_oi_render_field( array $args ): void {
		$id          = (string) ( $args['id'] ?? '' );
		$name        = (string) ( $args['name'] ?? '' );
		$label       = (string) ( $args['label'] ?? '' );
		$type        = (string) ( $args['type'] ?? 'text' );
		$value       = (string) ( $args['value'] ?? '' );
		$hint        = (string) ( $args['hint'] ?? '' );
		$required    = ! empty( $args['required'] );
		$rows        = (int) ( $args['rows'] ?? 4 );
		$placeholder = (string) ( $args['placeholder'] ?? '' );
		$min         = isset( $args['min'] ) ? (string) $args['min'] : '';
		$max         = isset( $args['max'] ) ? (string) $args['max'] : '';
		$accept      = (string) ( $args['accept'] ?? '' );
		$wide        = ! empty( $args['wide'] );
		$checked     = ! empty( $args['checked'] );

		$field_classes = [ 'pks-oi-field' ];
		if ( $wide ) {
			$field_classes[] = 'pks-oi-field--wide';
		}

		echo '<div class="' . esc_attr( implode( ' ', $field_classes ) ) . '">';
		if ( 'checkbox' === $type ) {
			echo '<label class="pks-oi-field__checkbox" for="' . esc_attr( $id ) . '">';
			printf(
				'<input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s />',
				esc_attr( $id ),
				esc_attr( $name ),
				$checked ? ' checked' : ''
			);
			echo '<span>' . esc_html( $label ) . '</span></label>';
		} else {
			printf(
				'<label class="pks-oi-field__label" for="%1$s">%2$s%3$s</label>',
				esc_attr( $id ),
				esc_html( $label ),
				$required ? '<span class="pks-oi-field__required" aria-hidden="true">*</span>' : ''
			);
			if ( 'textarea' === $type ) {
				printf(
					'<textarea class="pks-oi-field__control" id="%1$s" name="%2$s" rows="%3$d"%4$s>%5$s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					$rows,
					$required ? ' required' : '',
					esc_textarea( $value )
				);
			} else {
				printf(
					'<input class="pks-oi-field__control" type="%1$s" id="%2$s" name="%3$s" value="%4$s"%5$s%6$s%7$s%8$s%9$s />',
					esc_attr( $type ),
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					$required ? ' required' : '',
					'' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '',
					'' !== $min ? ' min="' . esc_attr( $min ) . '"' : '',
					'' !== $max ? ' max="' . esc_attr( $max ) . '"' : '',
					'' !== $accept ? ' accept="' . esc_attr( $accept ) . '"' : ''
				);
			}
		}
		if ( '' !== $hint ) {
			printf( '<p class="pks-oi-field__hint">%s</p>', esc_html( $hint ) );
		}
		echo '</div>';
	}
}

if ( ! function_exists( 'pks_oi_field_group_open' ) ) {
	function pks_oi_field_group_open( string $title, string $description = '' ): void {
		echo '<fieldset class="pks-oi-field-group">';
		printf( '<legend class="pks-oi-field-group__title">%s</legend>', esc_html( $title ) );
		if ( '' !== $description ) {
			printf( '<p class="pks-oi-field-group__description">%s</p>', esc_html( $description ) );
		}
		echo '<div class="pks-oi-form-grid">';
	}
}

if ( ! function_exists( 'pks_oi_field_group_close' ) ) {
	function pks_oi_field_group_close(): void {
		echo '</div></fieldset>';
	}
}

if ( ! function_exists( 'pks_oi_form_actions' ) ) {
	function pks_oi_form_actions( string $primary_label, bool $sticky = false, string $extra_class = '' ): void {
		$classes = trim( 'pks-oi-form__actions' . ( $sticky ? ' pks-oi-form__actions--sticky' : '' ) . ' ' . $extra_class );
		printf( '<div class="%s">', esc_attr( $classes ) );
		printf( '<button type="submit" class="button button-primary">%s</button>', esc_html( $primary_label ) );
		echo '</div>';
	}
}

if ( ! function_exists( 'pks_oi_panel_open' ) ) {
	function pks_oi_panel_open( string $title, bool $open = false ): void {
		printf( '<details class="pks-oi-panel"%s>', $open ? ' open' : '' );
		printf( '<summary class="pks-oi-panel__summary">%s</summary>', esc_html( $title ) );
		echo '<div class="pks-oi-panel__body">';
	}
}

if ( ! function_exists( 'pks_oi_panel_close' ) ) {
	function pks_oi_panel_close(): void {
		echo '</div></details>';
	}
}

if ( ! function_exists( 'pks_oi_render_filter_pills' ) ) {
	/**
	 * @param array<string, string> $filters slug => label
	 */
	function pks_oi_render_filter_pills( string $base_url, array $filters, string $current, string $param ): void {
		echo '<nav class="pks-oi-filter-pills" aria-label="' . esc_attr__( 'Filter', 'prikogstreg-online-invitations' ) . '"><ul>';
		foreach ( $filters as $key => $label ) {
			$url      = '' === $key || 'all' === $key ? remove_query_arg( $param, $base_url ) : add_query_arg( $param, $key, $base_url );
			$is_active = $current === $key || ( '' === $current && ( '' === $key || 'all' === $key ) );
			printf(
				'<li class="pks-oi-filter-pills__item%1$s"><a class="pks-oi-filter-pills__link" href="%2$s"%3$s>%4$s</a></li>',
				$is_active ? ' is-active' : '',
				esc_url( $url ),
				$is_active ? ' aria-current="true"' : '',
				esc_html( $label )
			);
		}
		echo '</ul></nav>';
	}
}

if ( ! function_exists( 'pks_oi_render_checklist_cards' ) ) {
	/**
	 * @param array<string, array{label:string,done:bool,detail:string,url?:string}> $checklist
	 */
	function pks_oi_render_checklist_cards( array $checklist ): void {
		echo '<ul class="pks-oi-checklist-cards">';
		foreach ( $checklist as $item ) {
			$done = ! empty( $item['done'] );
			$url  = (string) ( $item['url'] ?? '' );
			$class = $done ? 'is-done' : 'is-pending';
			echo '<li class="pks-oi-checklist-cards__item ' . esc_attr( $class ) . '">';
			if ( '' !== $url ) {
				printf( '<a class="pks-oi-checklist-cards__link" href="%s">', esc_url( $url ) );
			} else {
				echo '<div class="pks-oi-checklist-cards__link">';
			}
			printf( '<span class="pks-oi-checklist-cards__status" aria-hidden="true"></span>' );
			echo '<span class="pks-oi-checklist-cards__text">';
			printf( '<strong class="pks-oi-checklist-cards__label">%s</strong>', esc_html( (string) ( $item['label'] ?? '' ) ) );
			printf( '<span class="pks-oi-checklist-cards__detail">%s</span>', esc_html( (string) ( $item['detail'] ?? '' ) ) );
			echo '</span>';
			echo '' !== $url ? '</a>' : '</div>';
			echo '</li>';
		}
		echo '</ul>';
	}
}

if ( ! function_exists( 'pks_oi_render_card_open' ) ) {
	function pks_oi_render_card_open( string $title, string $modifier = '' ): void {
		printf( '<div class="pks-oi-card %s">', esc_attr( trim( $modifier ) ) );
		if ( '' !== $title ) {
			printf( '<h4 class="pks-oi-card__title">%s</h4>', esc_html( $title ) );
		}
		echo '<div class="pks-oi-card__body">';
	}
}

if ( ! function_exists( 'pks_oi_render_card_close' ) ) {
	function pks_oi_render_card_close(): void {
		echo '</div></div>';
	}
}

if ( ! function_exists( 'pks_oi_format_datetime_display' ) ) {
	function pks_oi_format_datetime_display( string $utc ): string {
		if ( '' === trim( $utc ) ) {
			return '';
		}
		$timestamp = strtotime( $utc . ' UTC' );
		if ( false === $timestamp ) {
			return $utc;
		}
		if ( function_exists( 'wp_date' ) ) {
			return wp_date( get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i' ), $timestamp );
		}
		return gmdate( 'Y-m-d H:i', $timestamp );
	}
}
