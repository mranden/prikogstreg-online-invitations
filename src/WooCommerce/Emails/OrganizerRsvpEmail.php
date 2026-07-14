<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\Emails;

final class OrganizerRsvpEmail extends AbstractOiEmail {

	protected string $oi_template = 'pks-oi-organizer-rsvp';

	public function __construct() {
		$this->id             = 'pks_oi_organizer_rsvp';
		$this->title          = __( 'Organizer RSVP notification', 'prikogstreg-online-invitations' );
		$this->description    = __( 'Notifies the organiser when a guest responds.', 'prikogstreg-online-invitations' );
		$this->subject        = __( 'New RSVP for {event_title}', 'prikogstreg-online-invitations' );
		$this->heading        = __( 'Guest response received', 'prikogstreg-online-invitations' );
		$this->customer_email = false;
		parent::__construct();
	}
}
