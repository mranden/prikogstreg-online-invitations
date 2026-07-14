<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit;

use PrikOgStreg\OnlineInvitations\Plugin;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class PluginTest extends TestCase {

	public function test_plugin_version_constant_matches_release(): void {
		$this->assertSame( '0.1.0', PKS_OI_VERSION );
	}

	public function test_database_schema_version_is_separate_from_plugin_version(): void {
		$this->assertSame( '1', PKS_OI_DB_VERSION );
		$this->assertNotSame( PKS_OI_VERSION, PKS_OI_DB_VERSION );
	}

	public function test_plugin_singleton_exposes_builder_service(): void {
		$plugin = Plugin::instance();

		$this->assertInstanceOf( Plugin::class, $plugin );
		$this->assertInstanceOf(
			\PrikOgStreg\OnlineInvitations\Builder\BuilderService::class,
			$plugin->builder()
		);
	}
}
