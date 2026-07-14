<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Support;

/**
 * Theme-aware template loader with allowlisted template names.
 *
 * Resolution order:
 * 1. child-theme/prikogstreg-online-invitations/{name}.php
 * 2. parent-theme/prikogstreg-online-invitations/{name}.php
 * 3. plugin/templates/{name}.php
 */
final class TemplateLoader {

	/** @var list<string> */
	private const ALLOWLIST = [
		'myaccount/dashboard',
		'myaccount/project-list',
		'myaccount/sidebar-nav',
		'myaccount/project-overview',
		'myaccount/project-design',
		'myaccount/project-event',
		'myaccount/project-preview',
		'myaccount/project-publish',
		'myaccount/project-guests',
		'myaccount/project-address-book',
		'myaccount/project-responses',
		'myaccount/project-wishlist',
		'myaccount/project-settings',
		'myaccount/project-photos',
		'myaccount/section-placeholder',
		'myaccount/not-found',
		'myaccount/project-edit',
		'public/invitation',
		'public/envelope',
		'public/poster',
		'public/rsvp-form',
		'public/wishlist',
		'public/photos',
		'public/unavailable',
		'emails/wrapper',
		'admin/support',
	];

	/** @var list<string> */
	private const PREFIX_ALLOWLIST = [
		'emails/',
	];

	public function register(): void {
		// Reserved for future template hook registration.
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public function render( string $template, array $args = [] ): void {
		$path = $this->locate( $template );

		if ( '' === $path || ! is_readable( $path ) ) {
			return;
		}

		if ( ! empty( $args ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- scoped template args.
			extract( $args, EXTR_SKIP );
		}

		include $path;
	}

	public function locate( string $template ): string {
		$template = $this->normalize_template_name( $template );

		if ( ! $this->is_allowed( $template ) ) {
			return '';
		}

		$relative = 'prikogstreg-online-invitations/' . $template . '.php';

		$theme_path = locate_template( [ $relative ] );
		if ( '' !== $theme_path ) {
			return $theme_path;
		}

		$plugin_path = PKS_OI_PLUGIN_PATH . 'templates/' . $template . '.php';

		return is_readable( $plugin_path ) ? $plugin_path : '';
	}

	private function normalize_template_name( string $template ): string {
		$template = str_replace( '\\', '/', $template );
		$template = trim( $template, '/' );
		$template = preg_replace( '#\.\.+#', '', $template ) ?? $template;

		return $template;
	}

	private function is_allowed( string $template ): bool {
		if ( in_array( $template, self::ALLOWLIST, true ) ) {
			return true;
		}

		foreach ( self::PREFIX_ALLOWLIST as $prefix ) {
			if ( str_starts_with( $template, $prefix ) ) {
				return true;
			}
		}

		return false;
	}
}
