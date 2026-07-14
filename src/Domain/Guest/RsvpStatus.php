<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Guest;

final class RsvpStatus {

	public const PENDING   = 'pending';
	public const ATTENDING = 'attending';
	public const DECLINED  = 'declined';
	public const MAYBE     = 'maybe';
}
