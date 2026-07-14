<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\Checkout;

use PrikOgStreg\OnlineInvitations\WooCommerce\Cart\InvitationCart;

/**
 * Requires a WooCommerce customer account when the cart contains an invitation.
 */
final class AccountRequirement {

	public function register(): void {
		add_filter( 'woocommerce_checkout_registration_required', [ $this, 'registration_required' ] );
		add_filter( 'woocommerce_checkout_registration_enabled', [ $this, 'registration_enabled' ] );
		add_filter( 'pre_option_woocommerce_enable_guest_checkout', [ $this, 'disable_guest_checkout_option' ] );
		add_action( 'woocommerce_checkout_process', [ $this, 'validate_account_requirement' ] );
		add_action( 'woocommerce_before_checkout_form', [ $this, 'render_account_notice' ], 5 );
	}

	public function registration_required( bool $required ): bool {
		return InvitationCart::cart_contains_invitation() ? true : $required;
	}

	public function registration_enabled( bool $enabled ): bool {
		return InvitationCart::cart_contains_invitation() ? true : $enabled;
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	public function disable_guest_checkout_option( $value ) {
		if ( InvitationCart::cart_contains_invitation() ) {
			return 'no';
		}

		return $value;
	}

	public function validate_account_requirement(): void {
		if ( ! InvitationCart::cart_contains_invitation() ) {
			return;
		}

		if ( is_user_logged_in() ) {
			return;
		}

		$create_account = isset( $_POST['createaccount'] ) && (bool) wc_string_to_bool( wp_unslash( (string) $_POST['createaccount'] ) );

		if ( $create_account ) {
			return;
		}

		wc_add_notice(
			__( 'An account is required to purchase an online invitation. Please create an account or log in to continue.', 'prikogstreg-online-invitations' ),
			'error'
		);
	}

	public function render_account_notice(): void {
		if ( ! InvitationCart::cart_contains_invitation() || is_user_logged_in() ) {
			return;
		}

		wc_print_notice(
			__( 'Online invitations require a customer account. You will receive a secure link to set your password after checkout — we never send passwords by e-mail.', 'prikogstreg-online-invitations' ),
			'notice'
		);
	}
}
