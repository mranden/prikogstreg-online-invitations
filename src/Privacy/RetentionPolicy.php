<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Privacy;

/**
 * Technical retention defaults — legal confirmation may override documented periods.
 */
final class RetentionPolicy {

	public const DELIVERY_LOG_MONTHS = 24;

	public const EVENT_LOG_MONTHS = 12;

	public const ERASED_GUEST_LABEL = 'Erased guest';

	public const EXPORTER_ID = 'pks-oi-online-invitations';

	public const ERASER_ID = 'pks-oi-online-invitations';
}
