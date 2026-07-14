<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Bootstrap;

/**
 * Plugin deactivation cleanup.
 */
final class Deactivation {

	public static function run(): void {
		flush_rewrite_rules( false );
	}
}
