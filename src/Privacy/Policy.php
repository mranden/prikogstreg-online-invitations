<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Privacy;

/**
 * Suggested privacy policy sections for site administrators.
 */
final class Policy {

	public function register(): void {
		add_action( 'admin_init', [ $this, 'register_policy_content' ] );
	}

	public function register_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<h2>' . esc_html__( 'Online invitations', 'prikogstreg-online-invitations' ) . '</h2>'
			. '<p>' . esc_html__( 'When you purchase or manage an online invitation, we store event details, guest lists, RSVP responses, optional wishlist reservations, and guest photo uploads you or your guests submit.', 'prikogstreg-online-invitations' ) . '</p>'
			. '<p>' . esc_html__( 'Guest personal links are sent by e-mail. We store only hashed tokens, not raw invitation URLs, in our database.', 'prikogstreg-online-invitations' ) . '</p>'
			. '<p>' . esc_html__( 'Guest photo uploads are moderated by the project owner before download. Photos are not published in a public gallery by default.', 'prikogstreg-online-invitations' ) . '</p>'
			. '<p>' . esc_html__( 'Projects remain available to the owner after the event for a limited period, then may be marked expired. Expiry does not automatically delete your data.', 'prikogstreg-online-invitations' ) . '</p>'
			. '<p>' . esc_html__( 'You may archive or permanently delete a project from My Account settings. WooCommerce order records may be retained separately for legal and accounting purposes.', 'prikogstreg-online-invitations' ) . '</p>'
			. '<p>' . esc_html__( 'Optional external wishlist links (for example Ønskeskyen) open third-party websites. We do not synchronize data with those services.', 'prikogstreg-online-invitations' ) . '</p>'
			. '<p>' . esc_html__( 'Rate-limiting identifiers are stored briefly using hashed values and expire automatically.', 'prikogstreg-online-invitations' ) . '</p>';

		wp_add_privacy_policy_content(
			__( 'Prikogstreg Online Invitations', 'prikogstreg-online-invitations' ),
			wp_kses_post( $content )
		);
	}
}
