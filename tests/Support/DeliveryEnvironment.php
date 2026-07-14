<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Support;

/**
 * Registers Action Scheduler and WooCommerce mailer stubs for delivery integration tests.
 */
final class DeliveryEnvironment {

	private static bool $bootstrapped = false;

	public static function bootstrap(): void {
		if ( self::$bootstrapped ) {
			return;
		}

		require_once dirname( __DIR__ ) . '/stubs/action-scheduler.php';
		self::$bootstrapped = true;
	}
}
