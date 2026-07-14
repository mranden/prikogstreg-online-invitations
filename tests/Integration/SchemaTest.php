<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration;

use PrikOgStreg\OnlineInvitations\Database\Schema;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class SchemaTest extends TestCase {

	public function test_definitions_include_all_eight_tables_in_order(): void {
		$definitions = Schema::get_definitions( 'wp_', 'DEFAULT CHARSET=utf8mb4' );

		$this->assertCount( 8, $definitions );
		$this->assertStringContainsString( 'wp_pks_oi_projects', $definitions[0] );
		$this->assertStringContainsString( 'wp_pks_oi_guests', $definitions[1] );
		$this->assertStringContainsString( 'wp_pks_oi_address_book', $definitions[2] );
		$this->assertStringContainsString( 'wp_pks_oi_wishlist_items', $definitions[3] );
		$this->assertStringContainsString( 'wp_pks_oi_wishlist_reservations', $definitions[4] );
		$this->assertStringContainsString( 'wp_pks_oi_photos', $definitions[5] );
		$this->assertStringContainsString( 'wp_pks_oi_deliveries', $definitions[6] );
		$this->assertStringContainsString( 'wp_pks_oi_events', $definitions[7] );
	}

	public function test_current_version_matches_constant(): void {
		$this->assertSame( 2, Schema::CURRENT_VERSION );
	}

	public function test_projects_table_includes_envelope_image_id_column(): void {
		$definitions = Schema::get_definitions( 'wp_', 'DEFAULT CHARSET=utf8mb4' );

		$this->assertStringContainsString( 'envelope_image_id bigint(20) unsigned NOT NULL DEFAULT 0', $definitions[0] );
	}
}
