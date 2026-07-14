<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Admin;

use PrikOgStreg\OnlineInvitations\Admin\Invitations\InvitationAdminQuery;
use PrikOgStreg\OnlineInvitations\Admin\ProjectAdminFilter;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoModerationStatus;
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

	public function test_admin_query_search_by_project_id(): void {
		$projects = $this->registry->projects();
		$projects->insert(
			[
				'project_id'         => 200,
				'storage_uuid'       => '33333333-3333-4333-8333-333333333333',
				'user_id'            => 1,
				'order_id'           => 10,
				'order_item_id'      => 20,
				'product_id'         => 30,
				'template_id'        => '30',
				'event_title'        => 'Summer party',
				'status'             => ProjectStatus::ACTIVE,
				'publication_status' => PublicationStatus::PUBLISHED,
			]
		);
		$projects->insert(
			[
				'project_id'         => 201,
				'storage_uuid'       => '44444444-4444-4444-8444-444444444444',
				'user_id'            => 2,
				'order_id'           => 11,
				'order_item_id'      => 21,
				'product_id'         => 31,
				'template_id'        => '31',
				'event_title'        => 'Winter gala',
				'status'             => ProjectStatus::ACTIVE,
				'publication_status' => PublicationStatus::UNPUBLISHED,
			]
		);

		$result = $projects->list_admin_query(
			[
				'filter' => ProjectAdminFilter::ALL,
				'search' => 'Summer',
				'page'   => 1,
			]
		);

		$this->assertCount( 1, $result['items'] );
		$this->assertSame( 200, (int) $result['items'][0]['project_id'] );
	}

	public function test_admin_query_publication_filter(): void {
		$projects = $this->registry->projects();
		$projects->insert(
			[
				'project_id'         => 300,
				'storage_uuid'       => '55555555-5555-4555-8555-555555555555',
				'user_id'            => 1,
				'order_id'           => 10,
				'order_item_id'      => 20,
				'product_id'         => 30,
				'template_id'        => '30',
				'status'             => ProjectStatus::ACTIVE,
				'publication_status' => PublicationStatus::PUBLISHED,
			]
		);
		$projects->insert(
			[
				'project_id'         => 301,
				'storage_uuid'       => '66666666-6666-4666-8666-666666666666',
				'user_id'            => 2,
				'order_id'           => 11,
				'order_item_id'      => 21,
				'product_id'         => 30,
				'template_id'        => '30',
				'status'             => ProjectStatus::ACTIVE,
				'publication_status' => PublicationStatus::UNPUBLISHED,
			]
		);

		$result = $projects->list_admin_query(
			[
				'filter'             => ProjectAdminFilter::ALL,
				'publication_status' => PublicationStatus::PUBLISHED,
				'page'               => 1,
			]
		);

		$this->assertCount( 1, $result['items'] );
		$this->assertSame( PublicationStatus::PUBLISHED, $result['items'][0]['publication_status'] );
	}

	public function test_batch_guest_and_photo_counts(): void {
		$projects = $this->registry->projects();
		$guests   = $this->registry->guests();
		$photos   = $this->registry->photos();

		$projects->insert(
			[
				'project_id'   => 400,
				'storage_uuid' => '77777777-7777-4777-8777-777777777777',
				'user_id'      => 1,
				'order_id'     => 10,
				'order_item_id'=> 20,
				'product_id'   => 30,
				'template_id'  => '30',
				'status'       => ProjectStatus::ACTIVE,
			]
		);

		$guests->insert(
			[
				'guest_id'    => 1,
				'project_id'  => 400,
				'display_name'=> 'Ada',
				'rsvp_status' => 'attending',
				'token_hash'  => str_repeat( 'a', 64 ),
			]
		);
		$photos->insert(
			[
				'photo_id'           => 1,
				'project_id'         => 400,
				'storage_uuid'       => '77777777-7777-4777-8777-777777777777',
				'relative_path'      => 'photos/1.jpg',
				'moderation_status'  => PhotoModerationStatus::PENDING,
			]
		);

		$guest_summary = $guests->batch_status_summaries( [ 400 ] );
		$photo_counts  = $photos->batch_moderation_counts( [ 400 ] );

		$this->assertSame( 1, $guest_summary[400]['total'] );
		$this->assertSame( 1, $guest_summary[400]['attending'] );
		$this->assertSame( 1, $photo_counts[400]['pending'] );
	}

	public function test_admin_filter_sanitizes_unknown_values(): void {
		$this->assertSame( ProjectAdminFilter::ALL, ProjectAdminFilter::sanitize( 'unknown' ) );
		$this->assertSame( ProjectAdminFilter::ACTIVE, ProjectAdminFilter::sanitize( 'active' ) );
	}

	public function test_invitation_admin_query_sanitizes_sort(): void {
		$query          = new InvitationAdminQuery();
		$query->orderby = 'invalid_column';
		$args           = $query->to_repository_args();
		$this->assertSame( 'invalid_column', $args['orderby'] );

		$repo = $this->registry->projects();
		$repo->insert(
			[
				'project_id'   => 500,
				'storage_uuid' => '88888888-8888-4888-8888-888888888888',
				'user_id'      => 1,
				'order_id'     => 10,
				'order_item_id'=> 20,
				'product_id'   => 30,
				'template_id'  => '30',
				'status'       => ProjectStatus::ACTIVE,
			]
		);

		$result = $repo->list_admin_query(
			[
				'filter'  => ProjectAdminFilter::ALL,
				'orderby' => 'not_allowed',
				'page'    => 1,
			]
		);
		$this->assertCount( 1, $result['items'] );
	}
}
