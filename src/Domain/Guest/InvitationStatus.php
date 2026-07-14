<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Guest;

final class InvitationStatus {

	public const NOT_SENT = 'not_sent';
	public const SENT     = 'sent';
	public const OPENED   = 'opened';
	public const BOUNCED  = 'bounced';
}
