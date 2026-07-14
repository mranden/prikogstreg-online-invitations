<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Delivery;

/**
 * Action Scheduler hook names and group for invitation deliveries.
 */
final class SchedulerMeta {

	public const GROUP = 'pks-oi';

	public const SEND_INVITATION = 'pks_oi_send_invitation';

	public const SEND_REMINDER = 'pks_oi_send_reminder';

	public const SEND_WELCOME = 'pks_oi_send_welcome';

	public const PROCESS_BATCH = 'pks_oi_process_delivery_batch';

	public const RESCHEDULE_REMINDERS = 'pks_oi_reschedule_reminders';

	public const EXPIRE_PROJECT = 'pks_oi_expire_project';

	public const SCAN_EXPIRATIONS = 'pks_oi_expire_projects';

	public const CLEANUP_TEMP = 'pks_oi_cleanup_temp';

	public const PRUNE_EVENT_LOGS = 'pks_oi_prune_event_logs';

	public const PRUNE_DELIVERY_LOGS = 'pks_oi_prune_delivery_logs';

	public const MAX_SEND_ATTEMPTS = 3;

	public const RETRY_DELAYS = [ 60, 300, 900 ];
}
