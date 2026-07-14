<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Photo;

final class PhotoModerationStatus {

	public const PENDING  = 'pending';
	public const APPROVED = 'approved';
	public const REJECTED = 'rejected';
}
