<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Delivery;

final class DeliveryStatus {

	public const QUEUED     = 'queued';
	public const PROCESSING = 'processing';
	public const SENDING    = 'processing';
	public const SENT       = 'sent';
	public const FAILED     = 'failed';
	public const CANCELLED  = 'cancelled';
	public const SKIPPED    = 'skipped';
}
