<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration;

use PrikOgStreg\OnlineInvitations\Database\Migrator;
use PrikOgStreg\OnlineInvitations\Database\Schema;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\Support\OptionsStore;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class MigratorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
	}

	public function test_clean_install_sets_db_version(): void {
		$wpdb     = new FakeWpdb();
		$migrator = new Migrator( $wpdb );

		$this->assertTrue( $migrator->install() );
		$this->assertSame( Schema::CURRENT_VERSION, $migrator->get_installed_version() );
		$this->assertFalse( $migrator->needs_migration() );
	}

	public function test_second_install_is_idempotent(): void {
		$wpdb     = new FakeWpdb();
		$migrator = new Migrator( $wpdb );

		$this->assertTrue( $migrator->install() );
		$this->assertTrue( $migrator->install() );
		$this->assertSame( Schema::CURRENT_VERSION, $migrator->get_installed_version() );
	}

	public function test_migration_retry_after_lock_expires(): void {
		OptionsStore::set(
			'pks_oi_migration_lock',
			[
				'acquired_at' => time() - 600,
				'expires_at'  => time() - 100,
			]
		);

		$wpdb     = new FakeWpdb();
		$migrator = new Migrator( $wpdb );

		$this->assertTrue( $migrator->install() );
		$this->assertSame( Schema::CURRENT_VERSION, $migrator->get_installed_version() );
	}

	public function test_install_fails_when_lock_is_active(): void {
		OptionsStore::set(
			'pks_oi_migration_lock',
			[
				'acquired_at' => time(),
				'expires_at'  => time() + 300,
			]
		);

		$wpdb     = new FakeWpdb();
		$migrator = new Migrator( $wpdb );

		$this->assertFalse( $migrator->install() );
		$this->assertSame( 'migration_lock_active', OptionsStore::get( Migrator::OPTION_MIGRATION_ERROR ) );
	}
}
