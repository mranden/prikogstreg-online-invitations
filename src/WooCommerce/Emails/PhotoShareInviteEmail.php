<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\Emails;

final class PhotoShareInviteEmail extends AbstractOiEmail {

	protected string $oi_template = 'pks-oi-photo-share-invite';

	public function __construct() {
		$this->id             = 'pks_oi_photo_share_invite';
		$this->title          = __( 'Photo share invitation', 'prikogstreg-online-invitations' );
		$this->description    = __( 'Invites guests to upload event photos via the dedicated photo page.', 'prikogstreg-online-invitations' );
		$this->subject        = __( 'Share your photos from {event_title}', 'prikogstreg-online-invitations' );
		$this->heading        = __( 'Share event photos', 'prikogstreg-online-invitations' );
		$this->customer_email = true;
		parent::__construct();
	}
}
