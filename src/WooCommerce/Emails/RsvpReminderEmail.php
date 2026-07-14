<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\Emails;

final class RsvpReminderEmail extends AbstractOiEmail {

	protected string $template_base = 'pks-oi-rsvp-reminder';

	public function __construct() {
		$this->id          = 'pks_oi_rsvp_reminder';
		$this->title       = __( 'RSVP reminder', 'prikogstreg-online-invitations' );
		$this->description = __( 'Reminder before the RSVP deadline.', 'prikogstreg-online-invitations' );
		$this->subject     = __( 'Reminder: please respond to {event_title}', 'prikogstreg-online-invitations' );
		$this->heading     = __( 'RSVP reminder', 'prikogstreg-online-invitations' );
		parent::__construct();
	}
}
