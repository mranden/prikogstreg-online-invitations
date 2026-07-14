<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests;

use Brain\Monkey;
use PrikOgStreg\OnlineInvitations\Tests\Support\OptionsStore;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		OptionsStore::reset();
		$GLOBALS['pks_oi_test_transients'] = [];
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
