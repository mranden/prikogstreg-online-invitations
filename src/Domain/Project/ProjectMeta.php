<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

/**
 * Order-item and project linkage meta keys.
 */
final class ProjectMeta {

	public const ORDER_ITEM_PROJECT_ID = '_pks_oi_project_id';

	public const WELCOME_SENT_OPTION_PREFIX = 'pks_oi_welcome_sent_';

	public const WELCOME_ACTION_HOOK = 'pks_oi_send_welcome';

	public const WELCOME_ACTION_GROUP = 'pks-oi';
}
