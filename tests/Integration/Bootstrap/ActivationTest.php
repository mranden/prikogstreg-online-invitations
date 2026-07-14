<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Bootstrap;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Bootstrap\Activation;
use PrikOgStreg\OnlineInvitations\Database\Migrator;
use PrikOgStreg\OnlineInvitations\Database\Schema;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\Support\OptionsStore;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class ActivationTest extends TestCase {

	public function test_activation_installs_schema_and_records_version(): void {
		global $wpdb;
		$wpdb = new FakeWpdb();

		Functions\when( 'post_type_exists' )->justReturn( true );
		Functions\when( 'get_role' )->justReturn( null );

		Activation::run();

		$this->assertSame( PKS_OI_VERSION, OptionsStore::get( 'pks_oi_version' ) );
		$this->assertSame( Schema::CURRENT_VERSION, OptionsStore::get( Migrator::OPTION_DB_VERSION ) );
		$this->assertNotFalse( OptionsStore::get( 'pks_oi_activated_at', false ) );
	}
}
