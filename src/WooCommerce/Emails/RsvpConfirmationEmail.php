<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\Emails;

final class RsvpConfirmationEmail extends AbstractOiEmail {

	protected string $oi_template = 'pks-oi-rsvp-confirmation';

	public function __construct() {
		$this->id          = 'pks_oi_rsvp_confirmation';
		$this->title       = __( 'RSVP confirmation', 'prikogstreg-online-invitations' );
		$this->description = __( 'Confirmation after a guest submits or changes an RSVP.', 'prikogstreg-online-invitations' );
		$this->subject     = __( 'Your RSVP for {event_title}', 'prikogstreg-online-invitations' );
		$this->heading     = __( 'RSVP received', 'prikogstreg-online-invitations' );
		parent::__construct();
	}
}
