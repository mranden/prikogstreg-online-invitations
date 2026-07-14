<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\MyAccount;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;
use PrikOgStreg\OnlineInvitations\MyAccount\ProjectSections;
use PrikOgStreg\OnlineInvitations\MyAccount\SectionNavBuilder;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class SectionNavBuilderTest extends TestCase {

	private SectionNavBuilder $builder;

	private RepositoryRegistry $registry;

	protected function setUp(): void {
		parent::setUp();

		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $number ): string => 1 === $number ? $single : $plural
		);

		$this->registry = new RepositoryRegistry( new FakeWpdb() );
		$this->builder  = new SectionNavBuilder(
			$this->registry->guests(),
			$this->registry->wishlist_items(),
			$this->registry->photos(),
			$this->registry->address_book()
		);
	}

	public function test_build_marks_core_setup_progress_from_project_state(): void {
		$projects = $this->registry->projects();
		$guests   = $this->registry->guests();

		$projects->insert(
			[
				'project_id'         => 301,
				'storage_uuid'       => 'aaaaaaaa-bbbb-4ccc-8ddd-000000000301',
				'user_id'            => 7,
				'order_id'           => 1,
				'order_item_id'      => 1,
				'product_id'         => 10,
				'template_id'        => '10',
				'status'             => ProjectStatus::ACTIVE,
				'publication_status' => PublicationStatus::UNPUBLISHED,
				'state_version'      => 2,
				'event_title'        => 'Birthday',
				'event_start_utc'    => '2026-08-01 14:00:00',
			]
		);

		$guests->insert(
			[
				'guest_id'   => 1,
				'project_id' => 301,
				'email'      => 'guest@example.com',
				'first_name' => 'Ada',
				'last_name'  => 'Lovelace',
			]
		);

		$project = $projects->find_by_id( 301 );
		$this->assertIsArray( $project );

		$nav = $this->builder->build( $project, ProjectSections::OVERVIEW, 7 );

		$this->assertSame( 3, $nav['progress']['completed'] );
		$this->assertSame( 3, $nav['progress']['total'] );
		$this->assertCount( 4, $nav['groups'] );

		$overview_item = $this->find_item( $nav['groups'], ProjectSections::OVERVIEW );
		$this->assertNotNull( $overview_item );
		$this->assertSame( 'complete', $overview_item['status'] );

		$guest_item = $this->find_item( $nav['groups'], ProjectSections::GUESTS );
		$this->assertNotNull( $guest_item );
		$this->assertSame( 'complete', $guest_item['status'] );
		$this->assertStringContainsString( '1 guest', $guest_item['meta'] );
		$this->assertNull( $this->find_item( $nav['groups'], ProjectSections::ADDRESS_BOOK ) );

		$this->assertSame( 'setup', $nav['groups'][0]['slug'] ?? '' );
		$this->assertSame( 'launch', $nav['groups'][1]['slug'] ?? '' );
		$this->assertSame( 'manage', $nav['groups'][2]['slug'] ?? '' );

		$setup_items = $nav['groups'][0]['items'] ?? [];
		$this->assertSame( ProjectSections::OVERVIEW, $setup_items[0]['slug'] ?? '' );
		$this->assertSame( ProjectSections::DESIGN, $setup_items[1]['slug'] ?? '' );
		$this->assertSame( ProjectSections::EVENT, $setup_items[2]['slug'] ?? '' );
		$this->assertSame( ProjectSections::GUESTS, $setup_items[3]['slug'] ?? '' );
	}

	public function test_build_keeps_stable_group_order_when_core_setup_complete(): void {
		$projects = $this->registry->projects();
		$guests   = $this->registry->guests();

		$projects->insert(
			[
				'project_id'         => 304,
				'storage_uuid'       => 'aaaaaaaa-bbbb-4ccc-8ddd-000000000304',
				'user_id'            => 7,
				'order_id'           => 1,
				'order_item_id'      => 1,
				'product_id'         => 10,
				'template_id'        => '10',
				'status'             => ProjectStatus::ACTIVE,
				'publication_status' => PublicationStatus::PUBLISHED,
				'state_version'      => 2,
				'event_title'        => 'Birthday',
				'event_start_utc'    => '2026-08-01 14:00:00',
			]
		);

		$guests->insert(
			[
				'guest_id'   => 2,
				'project_id' => 304,
				'email'      => 'guest@example.com',
				'first_name' => 'Ada',
				'last_name'  => 'Lovelace',
			]
		);

		$project = $projects->find_by_id( 304 );
		$this->assertIsArray( $project );

		$nav = $this->builder->build( $project, ProjectSections::GUESTS, 7 );

		$this->assertSame( 'setup', $nav['groups'][0]['slug'] ?? '' );
		$this->assertSame( 'launch', $nav['groups'][1]['slug'] ?? '' );
		$this->assertSame( 'manage', $nav['groups'][2]['slug'] ?? '' );
		$this->assertSame( ProjectSections::GUESTS, $nav['groups'][0]['items'][3]['slug'] ?? '' );
	}

	public function test_build_keeps_setup_first_when_core_setup_incomplete(): void {
		$projects = $this->registry->projects();

		$projects->insert(
			[
				'project_id'         => 303,
				'storage_uuid'       => 'aaaaaaaa-bbbb-4ccc-8ddd-000000000303',
				'user_id'            => 7,
				'order_id'           => 1,
				'order_item_id'      => 1,
				'product_id'         => 10,
				'template_id'        => '10',
				'status'             => ProjectStatus::ACTIVE,
				'publication_status' => PublicationStatus::UNPUBLISHED,
				'state_version'      => 2,
				'event_title'        => 'Birthday',
				'event_start_utc'    => '2026-08-01 14:00:00',
			]
		);

		$project = $projects->find_by_id( 303 );
		$this->assertIsArray( $project );

		$nav = $this->builder->build( $project, ProjectSections::OVERVIEW, 7 );

		$this->assertSame( 'setup', $nav['groups'][0]['slug'] ?? '' );
		$this->assertSame( ProjectSections::DESIGN, $nav['groups'][0]['items'][1]['slug'] ?? '' );
	}

	public function test_build_flags_design_attention_when_import_failed(): void {
		$projects = $this->registry->projects();

		$projects->insert(
			[
				'project_id'      => 302,
				'storage_uuid'    => 'aaaaaaaa-bbbb-4ccc-8ddd-000000000302',
				'user_id'         => 7,
				'order_id'        => 1,
				'order_item_id'   => 1,
				'product_id'      => 10,
				'template_id'     => '10',
				'state_version'   => 1,
				'last_error_code' => 'import_failed',
			]
		);

		$project = $projects->find_by_id( 302 );
		$this->assertIsArray( $project );

		$nav = $this->builder->build( $project, ProjectSections::DESIGN, 7 );
		$design_item = $this->find_item( $nav['groups'], ProjectSections::DESIGN );

		$this->assertNotNull( $design_item );
		$this->assertSame( 'attention', $design_item['status'] );
	}

	/**
	 * @param list<array{slug:string,label:string,items:list<array<string,mixed>>}> $groups
	 * @return array<string,mixed>|null
	 */
	private function find_item( array $groups, string $slug ): ?array {
		foreach ( $groups as $group ) {
			foreach ( $group['items'] as $item ) {
				if ( ( $item['slug'] ?? '' ) === $slug ) {
					return $item;
				}
			}
		}

		return null;
	}
}
