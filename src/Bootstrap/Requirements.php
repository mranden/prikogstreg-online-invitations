<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Bootstrap;

use PrikOgStreg\OnlineInvitations\Plugin;
use PrikOgStreg\OnlineInvitations\WooCommerce\Compatibility;

/**
 * Runtime requirement checks and safe boot entry.
 */
final class Requirements {

	public const MIN_PHP_VERSION      = '8.1.0';
	public const MIN_WP_VERSION       = '6.5';
	public const MIN_WC_VERSION       = '8.0.0';

	public static function boot(): void {
		if ( ! self::php_version_ok() ) {
			self::register_admin_notice(
				sprintf(
					/* translators: %s: minimum PHP version */
					__( 'Prikogstreg Online Invitations requires PHP %s or newer.', 'prikogstreg-online-invitations' ),
					self::MIN_PHP_VERSION
				)
			);

			return;
		}

		add_action( 'before_woocommerce_init', [ Compatibility::class, 'declare_hpos_compatibility' ] );
		add_action( 'plugins_loaded', [ self::class, 'on_plugins_loaded' ], 5 );
	}

	public static function on_plugins_loaded(): void {
		if ( ! self::wordpress_version_ok() ) {
			self::register_admin_notice(
				sprintf(
					/* translators: %s: minimum WordPress version */
					__( 'Prikogstreg Online Invitations requires WordPress %s or newer.', 'prikogstreg-online-invitations' ),
					self::MIN_WP_VERSION
				)
			);

			return;
		}

		if ( ! self::woocommerce_active() ) {
			self::register_admin_notice(
				__( 'Prikogstreg Online Invitations requires WooCommerce to be installed and active.', 'prikogstreg-online-invitations' )
			);

			return;
		}

		if ( ! self::woocommerce_version_ok() ) {
			self::register_admin_notice(
				sprintf(
					/* translators: %s: minimum WooCommerce version */
					__( 'Prikogstreg Online Invitations requires WooCommerce %s or newer.', 'prikogstreg-online-invitations' ),
					self::MIN_WC_VERSION
				)
			);

			return;
		}

		if ( ! Compatibility::is_hpos_enabled() ) {
			self::register_admin_notice(
				__( 'Prikogstreg Online Invitations requires WooCommerce High-Performance Order Storage (HPOS). Enable it under WooCommerce → Settings → Advanced → Features.', 'prikogstreg-online-invitations' )
			);

			return;
		}

		Plugin::instance()->boot();
	}

	public static function php_version_ok(): bool {
		return version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '>=' );
	}

	public static function wordpress_version_ok(): bool {
		global $wp_version;

		return isset( $wp_version ) && version_compare( (string) $wp_version, self::MIN_WP_VERSION, '>=' );
	}

	public static function woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	public static function woocommerce_version_ok(): bool {
		if ( ! defined( 'WC_VERSION' ) ) {
			return false;
		}

		return version_compare( WC_VERSION, self::MIN_WC_VERSION, '>=' );
	}

	public static function action_scheduler_available(): bool {
		return function_exists( 'as_schedule_single_action' ) || class_exists( 'ActionScheduler', false );
	}

	public static function register_admin_notice( string $message ): void {
		add_action(
			'admin_notices',
			static function () use ( $message ): void {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}

				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					esc_html( $message )
				);
			}
		);
	}
}
