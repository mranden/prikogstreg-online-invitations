<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\WooCommerce;

use PrikOgStreg\OnlineInvitations\WooCommerce\Compatibility;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class CompatibilityTest extends TestCase {

	public function test_is_hpos_enabled_false_when_order_util_missing(): void {
		$this->assertFalse( Compatibility::is_hpos_enabled() );
	}

	public function test_declare_hpos_compatibility_does_not_fatal_without_features_util(): void {
		Compatibility::declare_hpos_compatibility();
		$this->assertTrue( true );
	}
}
