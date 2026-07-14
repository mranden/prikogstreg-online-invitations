<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Builder;

/**
 * Safe no-op when the PDF Builder adapter is unavailable.
 *
 * Feature registrars check BuilderService::is_available() before enabling
 * online invitation product or project actions.
 */
final class BuilderUnavailable {

	public static function is_feature_enabled(): bool {
		return false;
	}
}
