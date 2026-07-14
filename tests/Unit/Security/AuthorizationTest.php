<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\Security;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Admin\Capabilities;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Security\Authorization;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class AuthorizationTest extends TestCase {

	private Authorization $authorization;

	private RepositoryRegistry $registry;

	protected function setUp(): void {
		parent::setUp();

		$this->registry      = new RepositoryRegistry( new FakeWpdb() );
		$this->authorization = new Authorization( $this->registry->projects() );

		$this->registry->projects()->insert(
			[
				'project_id'    => 501,
				'storage_uuid'  => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
				'user_id'       => 7,
				'order_id'      => 100,
				'order_item_id' => 900,
				'product_id'    => 10,
				'template_id'   => '10',
				'status'        => ProjectStatus::ACTIVE,
			]
		);
	}

	public function test_owner_can_view_project(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		Functions\when( 'current_user_can' )->alias(
			static fn( string $cap ) => Capabilities::MANAGE_OWN === $cap
		);

		$project = $this->authorization->resolve_viewable_project( 501 );
		$this->assertIsArray( $project );
		$this->assertFalse( $this->authorization->is_support_view( $project ) );
	}

	public function test_other_customer_cannot_view_project(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 99 );
		Functions\when( 'current_user_can' )->justReturn( false );

		$this->assertNull( $this->authorization->resolve_viewable_project( 501 ) );
	}

	public function test_support_staff_can_view_foreign_project(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 99 );
		Functions\when( 'current_user_can' )->alias(
			static fn( string $cap ) => Capabilities::SUPPORT === $cap
		);

		$project = $this->authorization->resolve_viewable_project( 501 );
		$this->assertIsArray( $project );
		$this->assertTrue( $this->authorization->is_support_view( $project ) );
	}
}
