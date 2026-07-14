<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration;

use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Database\TableNames;
use PrikOgStreg\OnlineInvitations\Domain\Guest\RsvpStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class RepositoryTest extends TestCase {

	private FakeWpdb $wpdb;

	private RepositoryRegistry $registry;

	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}

		$this->wpdb     = new FakeWpdb();
		$this->registry = new RepositoryRegistry( $this->wpdb );
	}

	public function test_project_repository_crud_and_prepared_lookup(): void {
		$projects = $this->registry->projects();

		$this->assertTrue(
			$projects->insert(
				[
					'project_id'    => 101,
					'storage_uuid'  => '11111111-1111-1111-1111-111111111111',
					'user_id'       => 7,
					'order_id'      => 55,
					'order_item_id' => 901,
					'product_id'    => 12,
					'template_id'   => 'classic',
					'status'        => ProjectStatus::DRAFT,
					'publication_status' => PublicationStatus::UNPUBLISHED,
				]
			)
		);

		$row = $projects->find_by_id( 101 );
		$this->assertIsArray( $row );
		$this->assertSame( '901', (string) $row['order_item_id'] );

		$this->assertTrue( $projects->update( 101, [ 'status' => ProjectStatus::ACTIVE ] ) );
		$updated = $projects->find_by_id( 101 );
		$this->assertSame( ProjectStatus::ACTIVE, $updated['status'] ?? '' );

		$by_item = $projects->find_by_order_item_id( 901 );
		$this->assertSame( '101', (string) ( $by_item['project_id'] ?? '' ) );
	}

	public function test_unique_order_item_id_is_enforced(): void {
		$projects = $this->registry->projects();
		$base     = [
			'storage_uuid'  => '22222222-2222-2222-2222-222222222222',
			'user_id'       => 1,
			'order_id'      => 1,
			'order_item_id' => 500,
			'product_id'    => 2,
			'template_id'   => 'classic',
		];

		$this->assertTrue( $projects->insert( array_merge( $base, [ 'project_id' => 1 ] ) ) );
		$this->assertFalse( $projects->insert( array_merge( $base, [ 'project_id' => 2, 'storage_uuid' => '33333333-3333-3333-3333-333333333333' ] ) ) );
	}

	public function test_guest_token_hash_uniqueness(): void {
		$guests = $this->registry->guests();

		$this->assertSame(
			1,
			$guests->insert(
				[
					'project_id'   => 10,
					'display_name' => 'Ada',
					'token_hash'   => str_repeat( 'a', 64 ),
					'rsvp_status'  => RsvpStatus::PENDING,
				]
			)
		);

		$this->assertSame(
			0,
			$guests->insert(
				[
					'project_id'   => 11,
					'display_name' => 'Bob',
					'token_hash'   => str_repeat( 'a', 64 ),
					'rsvp_status'  => RsvpStatus::PENDING,
				]
			)
		);
	}

	public function test_address_book_queries_are_owner_scoped(): void {
		$book = $this->registry->address_book();

		$owner_a = $book->insert(
			[
				'user_id'      => 42,
				'display_name' => 'Owner A Guest',
				'email'        => 'a@example.test',
			]
		);
		$book->insert(
			[
				'user_id'      => 99,
				'display_name' => 'Owner B Guest',
				'email'        => 'b@example.test',
			]
		);

		$rows = $book->list_active_for_user( 42 );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'Owner A Guest', $rows[0]['display_name'] );

		$this->assertNull( $book->find_by_id_for_user( $owner_a, 99 ) );
		$this->assertIsArray( $book->find_by_id_for_user( $owner_a, 42 ) );
	}

	public function test_project_domain_cleanup_removes_related_rows(): void {
		$tables   = new TableNames( $this->wpdb->prefix );
		$projects = $this->registry->projects();
		$guests   = $this->registry->guests();
		$events   = $this->registry->events();

		$projects->insert(
			[
				'project_id'    => 77,
				'storage_uuid'  => '44444444-4444-4444-4444-444444444444',
				'user_id'       => 1,
				'order_id'      => 1,
				'order_item_id' => 777,
				'product_id'    => 1,
				'template_id'   => 'classic',
			]
		);
		$guests->insert(
			[
				'project_id'   => 77,
				'display_name' => 'Guest',
				'token_hash'   => str_repeat( 'b', 64 ),
			]
		);
		$events->insert(
			[
				'project_id' => 77,
				'actor_type' => 'system',
				'event_type' => 'project_created',
			]
		);

		$guests->delete_by_project( 77 );
		$events->delete_by_project( 77 );
		$projects->delete_by_id( 77 );

		$this->assertSame( 0, $this->wpdb->table_count( $tables->projects() ) );
		$this->assertSame( 0, $this->wpdb->table_count( $tables->guests() ) );
		$this->assertSame( 0, $this->wpdb->table_count( $tables->events() ) );
	}
}
