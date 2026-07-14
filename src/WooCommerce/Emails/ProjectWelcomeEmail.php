<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\Emails;

final class ProjectWelcomeEmail extends AbstractOiEmail {

	protected string $oi_template = 'pks-oi-project-welcome';

	public function __construct() {
		$this->id             = 'pks_oi_project_welcome';
		$this->title          = __( 'Project welcome', 'prikogstreg-online-invitations' );
		$this->description    = __( 'Sent once when an invitation project is ready in My Account.', 'prikogstreg-online-invitations' );
		$this->subject        = __( 'Your invitation project is ready', 'prikogstreg-online-invitations' );
		$this->heading        = __( 'Welcome to your invitation project', 'prikogstreg-online-invitations' );
		$this->customer_email = false;
		parent::__construct();
	}
}
