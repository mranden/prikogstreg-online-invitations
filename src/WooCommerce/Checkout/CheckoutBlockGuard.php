<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\Checkout;

use PrikOgStreg\OnlineInvitations\WooCommerce\Cart\InvitationCart;

/**
 * Blocks Checkout Block checkout for invitation carts until a supported bridge exists.
 */
final class CheckoutBlockGuard {

	public function register(): void {
		add_action( 'template_redirect', [ $this, 'redirect_unsupported_checkout' ], 5 );
		add_action( 'woocommerce_store_api_checkout_order_processed', [ $this, 'guard_store_api_checkout' ], 1, 1 );
	}

	public function redirect_unsupported_checkout(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}

		if ( ! InvitationCart::cart_contains_invitation() ) {
			return;
		}

		if ( ! self::is_blocks_checkout_page() ) {
			return;
		}

		wc_add_notice(
			__( 'Online invitations cannot be purchased through the block-based checkout yet. Please use the classic checkout page or contact support.', 'prikogstreg-online-invitations' ),
			'error'
		);

		wp_safe_redirect( wc_get_cart_url() );
		exit;
	}

	public function guard_store_api_checkout( $order ): void {
		if ( ! InvitationCart::cart_contains_invitation() ) {
			return;
		}

		if ( ! self::is_blocks_checkout_page() ) {
			return;
		}

		throw new \Exception(
			esc_html__( 'Online invitations are not supported via the Checkout Block in this release.', 'prikogstreg-online-invitations' )
		);
	}

	public static function is_blocks_checkout_page(): bool {
		$page_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'checkout' ) : 0;
		if ( $page_id <= 0 ) {
			return false;
		}

		if ( function_exists( 'has_block' ) && has_block( 'woocommerce/checkout', $page_id ) ) {
			return true;
		}

		$content = (string) get_post_field( 'post_content', $page_id );

		return str_contains( $content, 'wp:woocommerce/checkout' );
	}
}
