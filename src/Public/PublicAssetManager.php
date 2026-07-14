<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

use PrikOgStreg\OnlineInvitations\Public\Endpoints;
use PrikOgStreg\OnlineInvitations\Public\PhotoShareEndpoints;

/**
 * Strips unrelated shop/theme assets from the public invitation route.
 */
final class PublicAssetManager {

	public function register(): void {
		add_action( 'wp', [ $this, 'maybe_isolate_shell' ], 5 );
		add_action( 'wp_enqueue_scripts', [ $this, 'dequeue_unrelated_assets' ], 9999 );
		add_action( 'wp_enqueue_scripts', [ $this, 'strip_foreign_assets' ], 99999 );
		add_filter( 'body_class', [ $this, 'add_body_class' ] );
		add_filter( 'show_admin_bar', [ $this, 'hide_admin_bar_on_shell' ] );
	}

	public function maybe_isolate_shell(): void {
		if ( ! $this->is_public_shell_page() ) {
			return;
		}

		remove_action( 'wp_head', '_admin_bar_bump_cb' );
		$this->remove_hook_callbacks_for_class( 'wp_footer', 'Prikogstreg_Minicart', 'add_custom_minicart_to_footer' );
		remove_action( 'wp_footer', 'woocommerce_output_all_notices', 10 );

		/**
		 * Allows themes/plugins to remove additional hooks on the invitation shell.
		 */
		do_action( 'pks_oi_public_isolate_shell' );
	}

	/**
	 * @param bool $show
	 */
	public function hide_admin_bar_on_shell( $show ): bool {
		if ( $this->is_public_shell_page() ) {
			return false;
		}

		return (bool) $show;
	}

	/**
	 * @param class-string $class_name
	 */
	private function remove_hook_callbacks_for_class( string $hook, string $class_name, string $method ): void {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $hook ] ) || ! is_object( $wp_filter[ $hook ] ) ) {
			return;
		}

		$callbacks = $wp_filter[ $hook ]->callbacks ?? [];
		foreach ( $callbacks as $priority => $handlers ) {
			foreach ( $handlers as $handler ) {
				$function = $handler['function'] ?? null;
				if (
					is_array( $function )
					&& is_object( $function[0] ?? null )
					&& $function[0]::class === $class_name
					&& ( $function[1] ?? '' ) === $method
				) {
					remove_action( $hook, $function, (int) $priority );
				}
			}
		}
	}

	public function dequeue_unrelated_assets(): void {
		if ( ! $this->is_public_shell_page() ) {
			return;
		}

		$handles = apply_filters(
			'pks_oi/public/dequeue_handles',
			$this->default_dequeue_handles()
		);

		foreach ( $handles as $handle ) {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
		}
	}

	public function strip_foreign_assets(): void {
		if ( ! $this->is_public_shell_page() ) {
			return;
		}

		global $wp_styles, $wp_scripts;

		if ( $wp_styles instanceof \WP_Styles ) {
			foreach ( array_keys( $wp_styles->registered ) as $handle ) {
				if ( ! $this->is_allowed_style_handle( (string) $handle ) ) {
					wp_dequeue_style( $handle );
					wp_deregister_style( $handle );
				}
			}
		}

		if ( $wp_scripts instanceof \WP_Scripts ) {
			foreach ( array_keys( $wp_scripts->registered ) as $handle ) {
				if ( ! $this->is_allowed_script_handle( (string) $handle ) ) {
					wp_dequeue_script( $handle );
					wp_deregister_script( $handle );
				}
			}
		}
	}

	/**
	 * @param list<string> $classes
	 * @return list<string>
	 */
	public function add_body_class( array $classes ): array {
		if ( $this->is_public_shell_page() ) {
			$classes[] = 'pks-oi-public-shell';
		}

		return $classes;
	}

	private function is_allowed_style_handle( string $handle ): bool {
		return str_starts_with( $handle, 'pks-oi-' );
	}

	private function is_allowed_script_handle( string $handle ): bool {
		return str_starts_with( $handle, 'pks-oi-' );
	}

	private function is_public_shell_page(): bool {
		return $this->is_invitation_page() || $this->is_photo_share_page();
	}

	/**
	 * @return list<string>
	 */
	private function default_dequeue_handles(): array {
		return [
			'theme-js',
			'theme-build-js',
			'product-js',
			'minicart-js',
			'gallery-js',
			'boxes-zoom-on-click-js',
			'prikogstreg-header',
			'prikogstreg-live-search',
			'prikogstreg-fonts',
			'admin-styles',
			'splide-js',
			'theme-style',
			'checkout-js',
			'checkout-style',
			'wc-cart-fragments',
			'woocommerce',
			'woocommerce-layout',
			'woocommerce-smallscreen',
			'woocommerce-general',
			'woocommerce-inline',
			'wc-blocks-style',
			'wc-blocks-vendors-style',
			'wc-blocks-checkout-style',
			'wc-order-attribution',
			'wc-order-attribution-js',
			'photoswipe',
			'photoswipe-ui-default',
			'zoom',
			'flexslider',
			'jquery-blockui',
			'jquery-ui-style',
			'jquery-core',
			'jquery',
			'dashicons',
			'admin-bar',
			'bpp-csrt-pdf-css',
			'bpp-cart-pdf-js',
			'bpp-sizes-js',
			'awdr-main-js',
			'awdr-dynamic-price-js',
			'woo_discount_pro_style',
			'woo_discount_pro_script-js',
			'sourcebuster-js-js',
			'shipmondo-service-point-selector-block-style',
		];
	}

	private function is_invitation_page(): bool {
		$token = get_query_var( Endpoints::QUERY_VAR );
		if ( is_string( $token ) && '' !== $token ) {
			$poster = get_query_var( Endpoints::POSTER_ASSET_QUERY_VAR );
			$envelope = get_query_var( Endpoints::ENVELOPE_ASSET_QUERY_VAR );

			return '' === (string) $poster && '1' !== (string) $envelope;
		}

		return false;
	}

	private function is_photo_share_page(): bool {
		$token = get_query_var( PhotoShareEndpoints::QUERY_VAR );

		return is_string( $token ) && '' !== $token;
	}
}
