<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Security;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Admin\Capabilities;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoServiceFactory;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;
use PrikOgStreg\OnlineInvitations\Security\Authorization;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;
use PrikOgStreg\OnlineInvitations\Storage\StorageRegistry;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class IdorTest extends TestCase {

	private FakeWpdb $wpdb;

	private RepositoryRegistry $repositories;

	private Authorization $authorization;

	protected function setUp(): void {
		parent::setUp();

		$this->wpdb          = new FakeWpdb();
		$this->repositories  = new RepositoryRegistry( $this->wpdb );
		$this->authorization = new Authorization( $this->repositories->projects() );

		$this->repositories->projects()->insert(
			[
				'project_id'    => 7001,
				'storage_uuid'  => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
				'user_id'       => 7,
				'order_id'      => 200,
				'order_item_id' => 7001,
				'product_id'    => 10,
				'template_id'   => '10',
				'status'        => ProjectStatus::ACTIVE,
			]
		);

		$this->repositories->projects()->insert(
			[
				'project_id'    => 7002,
				'storage_uuid'  => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
				'user_id'       => 8,
				'order_id'      => 201,
				'order_item_id' => 7002,
				'product_id'    => 10,
				'template_id'   => '10',
				'status'        => ProjectStatus::ACTIVE,
			]
		);
	}

	public function test_user_b_cannot_resolve_user_a_project(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 8 );
		Functions\when( 'current_user_can' )->justReturn( false );

		$this->assertNull( $this->authorization->resolve_viewable_project( 7001 ) );
	}

	public function test_guest_idor_returns_null_for_wrong_project(): void {
		$guest_id = $this->repositories->guests()->insert(
			[
				'project_id'   => 7001,
				'display_name' => 'Scoped Guest',
				'email'        => 'guest@example.com',
			]
		);

		$this->assertNull( $this->repositories->guests()->find_by_id_for_project( $guest_id, 7002 ) );
		$this->assertIsArray( $this->repositories->guests()->find_by_id_for_project( $guest_id, 7001 ) );
	}

	public function test_photo_idor_denies_cross_project_download(): void {
		$storage_root = sys_get_temp_dir() . '/pks-oi-idor-' . uniqid( '', true );
		$storage      = new StorageRegistry( $storage_root );
		$photos       = PhotoServiceFactory::create( $this->repositories, $storage );
		$auth         = new Authorization( $this->repositories->projects() );

		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		Functions\when( 'current_user_can' )->justReturn( true );

		$project_a = $this->repositories->projects()->find_by_id( 7001 );
		$this->assertIsArray( $project_a );

		$photo_id = $this->repositories->photos()->insert(
			[
				'project_id'         => 7002,
				'storage_uuid'       => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
				'guest_id'           => null,
				'original_filename'  => 'other.png',
				'relative_path'      => 'photos/approved/other.png',
				'mime_type'          => 'image/png',
				'moderation_status'  => 'approved',
			]
		);

		$result = $photos->resolve_download( $project_a, $photo_id, $auth );
		$this->assertFalse( $result['success'] ?? true );

		@rmdir( $storage_root );
	}

	public function test_order_scoped_project_lookup_isolated(): void {
		$by_order_a = $this->repositories->projects()->find_by_order_item_id( 7001 );
		$by_order_b = $this->repositories->projects()->find_by_order_item_id( 7002 );

		$this->assertIsArray( $by_order_a );
		$this->assertIsArray( $by_order_b );
		$this->assertSame( 7, (int) ( $by_order_a['user_id'] ?? 0 ) );
		$this->assertSame( 8, (int) ( $by_order_b['user_id'] ?? 0 ) );

		$this->assertNull( $this->repositories->projects()->find_by_order_item_id( 99999 ) );
	}

	public function test_support_capability_can_view_foreign_project(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 99 );
		Functions\when( 'current_user_can' )->alias(
			static fn( string $cap ) => Capabilities::SUPPORT === $cap
		);

		$project = $this->authorization->resolve_viewable_project( 7001 );
		$this->assertIsArray( $project );
		$this->assertTrue( $this->authorization->is_support_view( $project ) );
	}

	public function test_personal_token_does_not_resolve_for_other_project_guest(): void {
		$token = InvitationToken::generate();
		$this->repositories->guests()->insert(
			[
				'project_id'   => 7001,
				'display_name' => 'Token Guest',
				'token_hash'   => $token['hash'],
			]
		);

		$this->repositories->projects()->update(
			7002,
			[
				'generic_token_hash' => InvitationToken::generate()['hash'],
				'publication_status' => PublicationStatus::PUBLISHED,
				'state_version'      => 1,
			]
		);

		$guest = $this->repositories->guests()->find_by_token_hash( $token['hash'] );
		$this->assertIsArray( $guest );
		$this->assertSame( 7001, (int) ( $guest['project_id'] ?? 0 ) );
	}
}
