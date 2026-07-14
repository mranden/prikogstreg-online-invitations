<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\Emails;

final class PhotoNotificationEmail extends AbstractOiEmail {

	protected string $template_base = 'pks-oi-photo-upload';

	public function __construct() {
		$this->id             = 'pks_oi_photo_upload';
		$this->title          = __( 'Photo upload notification', 'prikogstreg-online-invitations' );
		$this->description    = __( 'Notifies the organiser when a guest uploads a photo.', 'prikogstreg-online-invitations' );
		$this->subject        = __( 'New guest photo for {event_title}', 'prikogstreg-online-invitations' );
		$this->heading        = __( 'New guest photo', 'prikogstreg-online-invitations' );
		$this->customer_email = false;
		parent::__construct();
	}
}
