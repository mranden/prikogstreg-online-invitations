<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit;

use PrikOgStreg\OnlineInvitations\Bootstrap\Requirements;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class RequirementsTest extends TestCase {

	public function test_php_version_minimum_is_8_1(): void {
		$this->assertSame( '8.1.0', Requirements::MIN_PHP_VERSION );
		$this->assertTrue( Requirements::php_version_ok() );
	}

	public function test_action_scheduler_check_does_not_fatal_when_missing(): void {
		$this->assertFalse( Requirements::action_scheduler_available() );
	}
}
