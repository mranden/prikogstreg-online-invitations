<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\MyAccount;

use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;
use PrikOgStreg\OnlineInvitations\MyAccount\AccountPresentation;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class AccountPresentationTest extends TestCase {

	private RepositoryRegistry $registry;

	protected function setUp(): void {
		parent::setUp();
		$this->registry = new RepositoryRegistry( new FakeWpdb() );
	}

	public function test_filter_user_project_count_returns_active_total(): void {
		$projects = $this->registry->projects();

		$projects->insert(
			[
				'project_id'         => 101,
				'storage_uuid'       => 'aaaaaaaa-bbbb-4ccc-8ddd-000000000101',
				'user_id'            => 7,
				'order_id'           => 1,
				'order_item_id'      => 1,
				'product_id'         => 10,
				'template_id'        => '10',
				'status'             => ProjectStatus::ACTIVE,
				'publication_status' => PublicationStatus::UNPUBLISHED,
			]
		);

		$projects->insert(
			[
				'project_id'         => 102,
				'storage_uuid'       => 'aaaaaaaa-bbbb-4ccc-8ddd-000000000102',
				'user_id'            => 7,
				'order_id'           => 2,
				'order_item_id'      => 2,
				'product_id'         => 10,
				'template_id'        => '10',
				'status'             => ProjectStatus::ACTIVE,
				'publication_status' => PublicationStatus::UNPUBLISHED,
			]
		);

		$projects->insert(
			[
				'project_id'    => 103,
				'storage_uuid'  => 'aaaaaaaa-bbbb-4ccc-8ddd-000000000103',
				'user_id'       => 99,
				'order_id'      => 3,
				'order_item_id' => 3,
				'product_id'    => 10,
				'template_id'   => '10',
			]
		);

		$presentation = new AccountPresentation( $projects );

		$this->assertSame( 2, $presentation->filter_user_project_count( 0, 7 ) );
	}

	public function test_filter_user_project_count_returns_zero_for_guest(): void {
		$presentation = new AccountPresentation( $this->registry->projects() );

		$this->assertSame( 0, $presentation->filter_user_project_count( 0, 0 ) );
	}

	public function test_filter_user_projects_nav_returns_primary_and_list_urls(): void {
		$projects = $this->registry->projects();

		$projects->insert(
			[
				'project_id'         => 201,
				'storage_uuid'       => 'aaaaaaaa-bbbb-4ccc-8ddd-000000000201',
				'user_id'            => 7,
				'order_id'           => 1,
				'order_item_id'      => 1,
				'product_id'         => 10,
				'template_id'        => '10',
				'status'             => ProjectStatus::ACTIVE,
				'publication_status' => PublicationStatus::UNPUBLISHED,
				'event_title'        => 'Sommerfest',
				'updated_at_utc'     => '2026-07-14 12:00:00',
			]
		);

		$presentation = new AccountPresentation( $projects );
		$nav          = $presentation->filter_user_projects_nav( [], 7, 5 );

		$this->assertSame( 1, $nav['count'] );
		$this->assertStringContainsString( 'online-invitations/', $nav['list_url'] );
		$this->assertStringContainsString( 'online-invitations/201/', $nav['primary_url'] );
		$this->assertCount( 1, $nav['projects'] );
		$this->assertSame( 'Sommerfest', $nav['projects'][0]['title'] );
		$this->assertSame( 201, $nav['projects'][0]['project_id'] );
	}

	public function test_filter_user_projects_nav_returns_empty_structure_for_guest(): void {
		$presentation = new AccountPresentation( $this->registry->projects() );
		$nav          = $presentation->filter_user_projects_nav( [], 0, 5 );

		$this->assertSame( 0, $nav['count'] );
		$this->assertSame( [], $nav['projects'] );
	}
}
