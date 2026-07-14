<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\Emails;

final class GuestInvitationEmail extends AbstractOiEmail {

	protected string $oi_template = 'pks-oi-guest-invitation';

	public function __construct() {
		$this->id          = 'pks_oi_guest_invitation';
		$this->title       = __( 'Guest invitation', 'prikogstreg-online-invitations' );
		$this->description = __( 'Personal invitation link for a guest.', 'prikogstreg-online-invitations' );
		$this->subject     = __( 'You are invited to {event_title}', 'prikogstreg-online-invitations' );
		$this->heading     = __( 'You are invited', 'prikogstreg-online-invitations' );
		parent::__construct();
	}
}
