<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\Emails;

/**
 * Registers invitation e-mail classes with WooCommerce.
 */
final class EmailRegistry {

	public function register(): void {
		add_filter( 'woocommerce_email_classes', [ $this, 'add_email_classes' ] );
	}

	/**
	 * @param array<string, \WC_Email> $emails
	 * @return array<string, \WC_Email>
	 */
	public function add_email_classes( array $emails ): array {
		if ( ! class_exists( '\WC_Email' ) ) {
			return $emails;
		}

		$emails['pks_oi_project_welcome']    = new ProjectWelcomeEmail();
		$emails['pks_oi_demo_invitation']    = new DemoInvitationEmail();
		$emails['pks_oi_guest_invitation']   = new GuestInvitationEmail();
		$emails['pks_oi_rsvp_reminder']        = new RsvpReminderEmail();
		$emails['pks_oi_rsvp_confirmation']  = new RsvpConfirmationEmail();
		$emails['pks_oi_organizer_rsvp']      = new OrganizerRsvpEmail();
		$emails['pks_oi_photo_upload']         = new PhotoNotificationEmail();
		$emails['pks_oi_photo_share_invite']   = new PhotoShareInviteEmail();

		return $emails;
	}
}
