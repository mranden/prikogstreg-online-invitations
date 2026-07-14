<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\Security;

use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class SqlInjectionRepositoryTest extends TestCase {

	private FakeWpdb $wpdb;

	private RepositoryRegistry $registry;

	protected function setUp(): void {
		parent::setUp();
		$this->wpdb     = new FakeWpdb();
		$this->registry = new RepositoryRegistry( $this->wpdb );
	}

	/**
	 * @return list<array{0:string}>
	 */
	public static function fuzz_payload_provider(): array {
		return [
			[ "' OR '1'='1" ],
			[ "'; DROP TABLE wp_pks_oi_projects; --" ],
			[ "1; DELETE FROM wp_pks_oi_guests WHERE '1'='1" ],
			[ "Robert'); DROP TABLE Students;--" ],
			[ '%_%' ],
		];
	}

	/**
	 * @dataProvider fuzz_payload_provider
	 */
	public function test_project_lookup_treats_fuzz_as_literal( string $payload ): void {
		$this->registry->projects()->insert(
			[
				'project_id'    => 9001,
				'storage_uuid'  => '11111111-1111-4111-8111-111111111111',
				'user_id'       => 7,
				'order_id'      => 100,
				'order_item_id' => 9001,
				'product_id'    => 10,
				'template_id'   => '10',
				'status'        => ProjectStatus::ACTIVE,
				'event_title'   => $payload,
			]
		);

		$this->assertNull( $this->registry->projects()->find_by_id( 0 ) );
		$this->assertNull( $this->registry->projects()->find_by_id( (int) $payload ) );

		$found = $this->registry->projects()->find_by_id( 9001 );
		$this->assertIsArray( $found );
		$this->assertSame( $payload, $found['event_title'] ?? '' );
		$this->assertSame( 1, $this->wpdb->table_count( 'wp_pks_oi_projects' ) );
	}

	/**
	 * @dataProvider fuzz_payload_provider
	 */
	public function test_guest_lookup_scoped_by_project_id( string $payload ): void {
		$this->registry->projects()->insert(
			[
				'project_id'    => 9002,
				'storage_uuid'  => '22222222-2222-4222-8222-222222222222',
				'user_id'       => 7,
				'order_id'      => 101,
				'order_item_id' => 9002,
				'product_id'    => 10,
				'template_id'   => '10',
				'status'        => ProjectStatus::ACTIVE,
			]
		);

		$guest_id = $this->registry->guests()->insert(
			[
				'project_id'   => 9002,
				'display_name' => $payload,
				'email'        => 'guest@example.com',
			]
		);

		$this->assertNull( $this->registry->guests()->find_by_id_for_project( $guest_id, 9999 ) );
		$guest = $this->registry->guests()->find_by_id_for_project( $guest_id, 9002 );
		$this->assertIsArray( $guest );
		$this->assertSame( $payload, $guest['display_name'] ?? '' );
	}
}
