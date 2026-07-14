<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Project;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Domain\Project\DemoInvitationService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectEntitlement;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectPreviewService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectPublishService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStateService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Security\Authorization;
use PrikOgStreg\OnlineInvitations\Security\PublishedHtmlSanitizer;
use PrikOgStreg\OnlineInvitations\Storage\StorageRegistry;
use PrikOgStreg\OnlineInvitations\Tests\Support\DeliveryEnvironment;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeBuilderAdapter;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class ProjectLifecycleTest extends TestCase {

	private string $storage_root;

	private FakeWpdb $wpdb;

	private RepositoryRegistry $repositories;

	private FakeBuilderAdapter $adapter;

	private BuilderService $builder;

	private ProjectStateService $state_service;

	private ProjectPublishService $publish_service;

	private ProjectPreviewService $preview_service;

	private DemoInvitationService $demo_service;

	private Authorization $authorization;

	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 ) . '/stubs/bpp/Builder_Adapter_Interface.php';

		$this->storage_root = sys_get_temp_dir() . '/pks-oi-lifecycle-' . uniqid( '', true );
		$this->wpdb         = new FakeWpdb();
		$this->repositories = new RepositoryRegistry( $this->wpdb );
		$this->adapter      = new FakeBuilderAdapter();

		Functions\when( 'has_filter' )->justReturn( true );
		Functions\when( 'apply_filters' )->justReturn( $this->adapter );
		Functions\when( 'do_action' )->justReturn( null );

		$this->builder = new BuilderService();
		$this->builder->resolve();

		DeliveryEnvironment::bootstrap();

		$storage = ( new StorageRegistry( $this->storage_root ) )->project_storage();
		$this->state_service = new ProjectStateService(
			$this->builder,
			$storage,
			$this->repositories->projects(),
			$this->repositories->events()
		);
		$this->publish_service = new ProjectPublishService(
			$this->builder,
			$storage,
			$this->repositories->projects(),
			$this->state_service,
			$this->repositories->events()
		);
		$this->preview_service = new ProjectPreviewService( $this->builder, $this->state_service );
		$this->demo_service    = new DemoInvitationService(
			$this->repositories->events(),
			new DeliveryQueueService( $this->repositories->deliveries() )
		);
		$this->authorization   = new Authorization( $this->repositories->projects() );
	}

	protected function tearDown(): void {
		$this->delete_storage_tree( $this->storage_root );
		parent::tearDown();
	}

	public function test_owner_save_rejected_as_read_only(): void {
		$project = $this->seed_imported_project();

		$result = $this->state_service->save_design_state(
			$project,
			[
				'field'          => [ 'uuid-1' => [ 'text' => 'Updated' ] ],
				'page'           => [ '<section>Updated page</section>' ],
				'size'           => 'a5',
				'format'         => 'flat',
				'schema_version' => '1',
			],
			1
		);

		$this->assertSame( 'design_read_only', $result['error'] ?? null );
		$this->assertSame( 403, $result['code'] ?? null );

		$updated = $this->repositories->projects()->find_by_id( (int) $project['project_id'] );
		$this->assertSame( 1, (int) ( $updated['state_version'] ?? 0 ) );
	}

	public function test_other_user_cannot_resolve_project_for_save(): void {
		$this->seed_imported_project();
		Functions\when( 'get_current_user_id' )->justReturn( 99 );
		Functions\when( 'current_user_can' )->justReturn( false );

		$this->assertNull( $this->authorization->resolve_viewable_project( 3001 ) );
	}

	public function test_stale_version_still_rejected_as_read_only(): void {
		$project = $this->seed_imported_project();

		$result = $this->state_service->save_design_state(
			$project,
			[
				'field'          => [ 'uuid-1' => [ 'text' => 'Stale' ] ],
				'page'           => [ '<section>Stale</section>' ],
				'size'           => 'a5',
				'format'         => 'flat',
				'schema_version' => '1',
			],
			0
		);

		$this->assertSame( 'design_read_only', $result['error'] ?? null );
		$this->assertSame( 403, $result['code'] ?? null );
	}

	public function test_invalid_state_still_rejected_as_read_only(): void {
		$project = $this->seed_imported_project();
		$this->adapter->with_save_state(
			new class() {
				public function get_error_code(): string {
					return 'invalid_state';
				}
			}
		);

		$result = $this->state_service->save_design_state(
			$project,
			[ 'page' => [] ],
			1
		);

		$this->assertSame( 'design_read_only', $result['error'] ?? null );
		$this->assertSame( 403, $result['code'] ?? null );
	}

	public function test_publish_fails_when_public_html_is_unsafe(): void {
		$project = $this->seed_publishable_project();
		$this->adapter->with_render_public_html( '<script>alert(1)</script>' );

		$result = $this->publish_service->publish( $project );

		$this->assertFalse( $result['success'] ?? true );
		$this->assertSame( 'published_html_unsafe', $result['error'] ?? null );
	}

	public function test_publish_falls_back_to_editable_pages_when_adapter_html_empty(): void {
		$project = $this->seed_publishable_project();
		$this->adapter->with_render_public_html( '<div class="bpp-public-invitation" data-bpp-schema-version="1"></div>' );

		$result = $this->publish_service->publish( $project );

		$this->assertTrue( $result['success'] ?? false );
	}

	public function test_publish_rejects_empty_published_html_when_no_page_content(): void {
		$project = $this->seed_publishable_project();
		$empty_wrapper = '<div class="bpp-public-invitation" data-bpp-schema-version="1"></div>';
		$this->adapter->with_render_public_html( $empty_wrapper );
		$this->adapter->with_load_state(
			[
				'schema_version' => '1',
				'page'           => [ $empty_wrapper ],
				'field'          => [],
			]
		);

		$storage = ( new StorageRegistry( $this->storage_root ) )->project_storage();
		$storage->save_state(
			[
				'project_id'             => (int) $project['project_id'],
				'storage_uuid'           => (string) $project['storage_uuid'],
				'builder_schema_version' => '1',
				'product_id'             => (int) $project['product_id'],
				'template_id'            => (string) $project['template_id'],
				'expected_state_version' => (int) $project['state_version'],
				'state_json'             => '{"schema_version":"1","pages":[{"index":1}]}',
				'pages'                  => [
					[ 'index' => 1, 'html' => $empty_wrapper ],
				],
			]
		);

		$result = $this->publish_service->publish( $project );

		$this->assertFalse( $result['success'] ?? true );
		$this->assertSame( 'empty_published_html', $result['error'] ?? null );
	}

	public function test_preview_does_not_track_opens(): void {
		$project = $this->seed_imported_project();
		$preview = $this->preview_service->render_preview( $project );

		$this->assertFalse( $preview['track_opens'] );
		$this->assertStringContainsString( 'Preview page', $preview['html'] );
	}

	public function test_demo_send_succeeds_once_then_rate_limits(): void {
		$project = $this->seed_imported_project();
		$owner   = (int) $project['user_id'];

		$first = $this->demo_service->send_demo( $project, $owner );
		$this->assertTrue( $first['success'] ?? false );
		$this->assertNotEmpty( $first['preview_url'] ?? '' );

		$second = $this->demo_service->send_demo( $project, $owner );
		$this->assertFalse( $second['success'] ?? true );
		$this->assertSame( 'demo_rate_limited', $second['error'] ?? null );
	}

	public function test_sanitizer_blocks_inline_handlers(): void {
		$this->expectException( \InvalidArgumentException::class );
		PublishedHtmlSanitizer::sanitize( '<div onclick="evil()">x</div>' );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function seed_imported_project(): array {
		$uuid = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
		$this->repositories->projects()->insert(
			[
				'project_id'    => 3001,
				'storage_uuid'  => $uuid,
				'user_id'       => 7,
				'order_id'      => 100,
				'order_item_id' => 501,
				'product_id'    => 10,
				'template_id'   => '10',
				'status'        => ProjectStatus::ACTIVE,
				'state_version' => 0,
			]
		);

		$storage = ( new StorageRegistry( $this->storage_root ) )->project_storage();
		$import  = $storage->import_from_builder_state(
			[
				'project_id'             => 3001,
				'storage_uuid'           => $uuid,
				'builder_schema_version' => '1',
				'product_id'             => 10,
				'template_id'            => '10',
			],
			[
				'field'          => [ 'uuid-1' => [ 'text' => 'Hello' ] ],
				'page'           => [ '<section>Imported page</section>' ],
				'size'           => 'a5',
				'format'         => 'flat',
				'schema_version' => '1',
			]
		);

		$this->repositories->projects()->update(
			3001,
			[
				'state_version'       => (int) $import['state_version'],
				'state_manifest_path' => (string) $import['state_manifest_path'],
				'status'              => ProjectStatus::ACTIVE,
				'last_error_code'     => '',
			]
		);

		$project = $this->repositories->projects()->find_by_id( 3001 );
		$this->assertIsArray( $project );

		return $project;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function seed_publishable_project(): array {
		$project = $this->seed_imported_project();
		$this->repositories->projects()->update(
			(int) $project['project_id'],
			[
				'event_title'     => 'Birthday',
				'event_start_utc' => '2026-08-01 14:00:00',
			]
		);

		$updated = $this->repositories->projects()->find_by_id( (int) $project['project_id'] );
		$this->assertIsArray( $updated );
		$this->assertTrue( ProjectEntitlement::can_publish_project( $updated ) );

		return $updated;
	}

	private function delete_storage_tree( string $root ): void {
		if ( ! is_dir( $root ) ) {
			return;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( $file->isDir() ) {
				@rmdir( $file->getPathname() );
			} else {
				@unlink( $file->getPathname() );
			}
		}

		@rmdir( $root );
	}
}
