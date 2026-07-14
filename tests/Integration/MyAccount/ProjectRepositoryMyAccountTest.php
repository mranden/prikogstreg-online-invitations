<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\MyAccount;

use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;
use PrikOgStreg\OnlineInvitations\MyAccount\Endpoints;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;
use PrikOgStreg\OnlineInvitations\Tests\Support\OptionsStore;

final class ProjectRepositoryMyAccountTest extends TestCase {

	private RepositoryRegistry $registry;

	protected function setUp(): void {
		parent::setUp();
		$this->registry = new RepositoryRegistry( new FakeWpdb() );
	}

	public function test_list_summary_for_user_is_paginated_and_owner_scoped(): void {
		$projects = $this->registry->projects();

		for ( $i = 1; $i <= 12; ++$i ) {
			$projects->insert(
				[
					'project_id'         => 1000 + $i,
					'storage_uuid'       => sprintf( 'aaaaaaaa-bbbb-4ccc-8ddd-%012d', $i ),
					'user_id'            => 7,
					'order_id'           => $i,
					'order_item_id'      => 500 + $i,
					'product_id'         => 10,
					'template_id'        => '10',
					'status'             => ProjectStatus::ACTIVE,
					'publication_status' => PublicationStatus::UNPUBLISHED,
					'updated_at_utc'     => sprintf( '2026-07-%02d 10:00:00', min( 28, $i ) ),
				]
			);
		}

		$projects->insert(
			[
				'project_id'    => 2000,
				'storage_uuid'  => 'bbbbbbbb-bbbb-4ccc-8ddd-eeeeeeeeeeee',
				'user_id'       => 99,
				'order_id'      => 1,
				'order_item_id' => 999,
				'product_id'    => 10,
				'template_id'   => '10',
			]
		);

		$page_one = $projects->list_summary_for_user( 7, 1, 10 );
		$this->assertCount( 10, $page_one['items'] );
		$this->assertSame( 12, $page_one['total'] );

		$page_two = $projects->list_summary_for_user( 7, 2, 10 );
		$this->assertCount( 2, $page_two['items'] );
	}

	public function test_owned_by_and_find_owned_by_id(): void {
		$projects = $this->registry->projects();
		$projects->insert(
			[
				'project_id'    => 321,
				'storage_uuid'  => 'cccccccc-bbbb-4ccc-8ddd-eeeeeeeeeeee',
				'user_id'       => 42,
				'order_id'      => 1,
				'order_item_id' => 88,
				'product_id'    => 10,
				'template_id'   => '10',
			]
		);

		$this->assertTrue( $projects->owned_by( 321, 42 ) );
		$this->assertFalse( $projects->owned_by( 321, 7 ) );
		$this->assertIsArray( $projects->find_owned_by_id( 321, 42 ) );
		$this->assertNull( $projects->find_owned_by_id( 321, 7 ) );
	}

	public function test_rewrite_flush_only_when_version_changes(): void {
		OptionsStore::reset();
		Endpoints::maybe_flush_rewrites();
		$this->assertSame( Endpoints::REWRITE_VERSION, get_option( Endpoints::REWRITE_VERSION_OPTION ) );

		$before = OptionsStore::$values[ Endpoints::REWRITE_VERSION_OPTION ] ?? null;
		Endpoints::maybe_flush_rewrites();
		$this->assertSame( $before, get_option( Endpoints::REWRITE_VERSION_OPTION ) );
	}
}
