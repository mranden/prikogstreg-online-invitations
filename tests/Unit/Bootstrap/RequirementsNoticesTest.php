<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\Bootstrap;

use PrikOgStreg\OnlineInvitations\Bootstrap\Requirements;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class RequirementsNoticesTest extends TestCase {

	public function test_wordpress_version_check_uses_global_version(): void {
		global $wp_version;
		$wp_version = '6.4.0';
		$this->assertFalse( Requirements::wordpress_version_ok() );

		$wp_version = '6.5.0';
		$this->assertTrue( Requirements::wordpress_version_ok() );
	}

	public function test_woocommerce_active_requires_class(): void {
		$this->assertFalse( Requirements::woocommerce_active() );
	}

	public function test_woocommerce_version_requires_constant(): void {
		$this->assertFalse( Requirements::woocommerce_version_ok() );
	}

	public function test_register_admin_notice_method_exists(): void {
		$this->assertTrue( method_exists( Requirements::class, 'register_admin_notice' ) );
	}
}
