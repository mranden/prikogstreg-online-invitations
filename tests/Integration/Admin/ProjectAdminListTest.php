<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Admin;

use PrikOgStreg\OnlineInvitations\Admin\ProjectAdminFilter;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class ProjectAdminListTest extends TestCase {

	private RepositoryRegistry $registry;

	protected function setUp(): void {
		parent::setUp();
		$this->registry = new RepositoryRegistry( new FakeWpdb() );
	}

	public function test_admin_list_filters_active_and_deactivated_projects(): void {
		$projects = $this->registry->projects();

		$projects->insert(
			[
				'project_id'         => 100,
				'storage_uuid'       => '11111111-1111-4111-8111-111111111111',
				'user_id'            => 1,
				'order_id'           => 10,
				'order_item_id'      => 20,
				'product_id'         => 30,
				'template_id'        => '30',
				'status'             => ProjectStatus::ACTIVE,
				'publication_status' => PublicationStatus::UNPUBLISHED,
				'updated_at_utc'     => '2026-07-14 12:00:00',
			]
		);

		$projects->insert(
			[
				'project_id'         => 101,
				'storage_uuid'       => '22222222-2222-4222-8222-222222222222',
				'user_id'            => 2,
				'order_id'           => 11,
				'order_item_id'      => 21,
				'product_id'         => 30,
				'template_id'        => '30',
				'status'             => ProjectStatus::RESTRICTED,
				'publication_status' => PublicationStatus::UNPUBLISHED,
				'updated_at_utc'     => '2026-07-13 12:00:00',
			]
		);

		$this->assertSame( 2, $projects->count_admin_by_filter( ProjectAdminFilter::ALL ) );
		$this->assertSame( 1, $projects->count_admin_by_filter( ProjectAdminFilter::ACTIVE ) );
		$this->assertSame( 1, $projects->count_admin_by_filter( ProjectAdminFilter::DEACTIVATED ) );

		$active = $projects->list_admin_summaries( ProjectAdminFilter::ACTIVE, 1, 20 );
		$this->assertCount( 1, $active['items'] );
		$this->assertSame( 100, (int) $active['items'][0]['project_id'] );
	}

	public function test_admin_filter_sanitizes_unknown_values(): void {
		$this->assertSame( ProjectAdminFilter::ALL, ProjectAdminFilter::sanitize( 'unknown' ) );
		$this->assertSame( ProjectAdminFilter::ACTIVE, ProjectAdminFilter::sanitize( 'active' ) );
	}
}
