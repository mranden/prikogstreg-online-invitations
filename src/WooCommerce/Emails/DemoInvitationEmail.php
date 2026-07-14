<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\Emails;

final class DemoInvitationEmail extends AbstractOiEmail {

	protected string $oi_template = 'pks-oi-demo-invitation';

	public function __construct() {
		$this->id          = 'pks_oi_demo_invitation';
		$this->title       = __( 'Demo invitation', 'prikogstreg-online-invitations' );
		$this->description = __( 'Owner demo-to-self preview link.', 'prikogstreg-online-invitations' );
		$this->subject     = __( 'Your demo invitation preview', 'prikogstreg-online-invitations' );
		$this->heading     = __( 'Demo invitation', 'prikogstreg-online-invitations' );
		parent::__construct();
	}
}
