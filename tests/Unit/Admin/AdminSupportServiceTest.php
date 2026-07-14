<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Admin\AdminSupportService;
use PrikOgStreg\OnlineInvitations\Admin\Capabilities;
use PrikOgStreg\OnlineInvitations\Admin\Invitations\InvitationPreviewController;
use PrikOgStreg\OnlineInvitations\Admin\ProjectAdminListViewModel;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Guest\RsvpStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectEventService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectLifecycleAudit;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class AdminSupportServiceTest extends TestCase {

	private RepositoryRegistry $registry;

	protected function setUp(): void {
		parent::setUp();
		$this->registry = new RepositoryRegistry( new FakeWpdb() );
		Functions\when( 'add_query_arg' )->alias(
			static function ( array $args, string $url ): string {
				$parts = [];
				foreach ( $args as $key => $value ) {
					$parts[] = rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
				}

				return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . implode( '&', $parts );
			}
		);
	}

	public function test_admin_event_save_records_audit(): void {
		$projects = $this->registry->projects();
		$events   = $this->registry->events();
		$guests   = $this->registry->guests();
		$audit    = new ProjectLifecycleAudit( $events );

		$projects->insert(
			[
				'project_id'    => 42,
				'storage_uuid'  => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
				'user_id'       => 1,
				'order_id'      => 9,
				'order_item_id' => 1,
				'product_id'    => 3,
				'template_id'   => '3',
				'event_title'   => 'Old title',
				'status'        => 'active',
				'state_version' => 1,
			]
		);

		$service = new AdminSupportService(
			$projects,
			$guests,
			new ProjectEventService( $projects, $events ),
			$audit
		);

		$result = $service->save_event_details(
			(array) $projects->find_by_id( 42 ),
			[ 'event_title' => 'New title' ]
		);

		$this->assertTrue( $result['success'] );
		$stored = $projects->find_by_id( 42 );
		$this->assertSame( 'New title', $stored['event_title'] );

		$logged = $events->list_recent_for_project( 42, 5 );
		$this->assertNotEmpty( $logged );
		$this->assertSame( 'admin.event_details_changed', $logged[0]['event_type'] );
	}

	public function test_admin_guest_update_validates_rsvp(): void {
		$projects = $this->registry->projects();
		$events   = $this->registry->events();
		$guests   = $this->registry->guests();
		$audit    = new ProjectLifecycleAudit( $events );

		$projects->insert(
			[
				'project_id'    => 43,
				'storage_uuid'  => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
				'user_id'       => 1,
				'order_id'      => 9,
				'order_item_id' => 1,
				'product_id'    => 3,
				'template_id'   => '3',
				'status'        => 'active',
				'state_version' => 1,
			]
		);
		$guests->insert(
			[
				'guest_id'     => 7,
				'project_id'   => 43,
				'display_name' => 'Guest',
				'rsvp_status'  => RsvpStatus::PENDING,
				'token_hash'   => str_repeat( 'b', 64 ),
			]
		);

		$service = new AdminSupportService( $projects, $guests, new ProjectEventService( $projects, $events ), $audit );
		$project = (array) $projects->find_by_id( 43 );
		$guest   = (array) $guests->find_by_id( 7 );

		$fail = $service->update_guest( $project, $guest, [ 'display_name' => 'Guest', 'rsvp_status' => 'invalid' ] );
		$this->assertFalse( $fail['success'] );

		$ok = $service->update_guest(
			$project,
			$guest,
			[ 'display_name' => 'Updated Guest', 'rsvp_status' => RsvpStatus::ATTENDING ]
		);
		$this->assertTrue( $ok['success'] );
	}

	public function test_preview_url_contains_nonce_action(): void {
		Functions\when( 'admin_url' )->justReturn( 'https://example.test/wp-admin/admin-post.php' );
		Functions\when( 'wp_nonce_url' )->alias(
			static fn( string $url, string $action ): string => $url . '&_wpnonce=test&nonce_action=' . rawurlencode( $action )
		);

		$url = InvitationPreviewController::preview_url( 99, 'draft' );
		$this->assertStringContainsString( 'project_id=99', $url );
		$this->assertStringContainsString( InvitationPreviewController::NONCE_ACTION . '_99_draft', $url );
	}

	public function test_detail_url_uses_new_page_slug(): void {
		Functions\when( 'admin_url' )->justReturn( 'https://example.test/wp-admin/admin.php' );

		$url = ProjectAdminListViewModel::detail_url( 12, 'guests' );
		$this->assertStringContainsString( 'page=' . ProjectAdminListViewModel::PAGE_SLUG, $url );
		$this->assertStringContainsString( 'project_id=12', $url );
		$this->assertStringContainsString( 'tab=guests', $url );
	}

	public function test_capabilities_include_admin_view_and_edit(): void {
		$this->assertContains( Capabilities::VIEW, Capabilities::admin_caps() );
		$this->assertContains( Capabilities::EDIT, Capabilities::all() );
	}
}
