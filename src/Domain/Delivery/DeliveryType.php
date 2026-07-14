<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Delivery;

final class DeliveryType {

	public const WELCOME            = 'welcome';
	public const DEMO               = 'demo';
	public const GUEST_INVITATION   = 'guest_invitation';
	public const RSVP_REMINDER      = 'rsvp_reminder';
	public const RSVP_CONFIRMATION  = 'rsvp_confirmation';
	public const ORGANIZER_RSVP     = 'organizer_rsvp';
	public const PHOTO_NOTIFICATION = 'photo_notification';
	public const PHOTO_SHARE_INVITE = 'photo_share_invite';
}
