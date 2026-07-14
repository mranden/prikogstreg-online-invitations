<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Project;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectEntitlement;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectFactory;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectMeta;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Scheduling\SchedulerRegistrar;
use PrikOgStreg\OnlineInvitations\Scheduling\WelcomeScheduler;
use PrikOgStreg\OnlineInvitations\Storage\StorageRegistry;
use PrikOgStreg\OnlineInvitations\WooCommerce\Cart\CartPayload;
use PrikOgStreg\OnlineInvitations\WooCommerce\Orders\ProjectCreationLock;
use PrikOgStreg\OnlineInvitations\WooCommerce\Orders\ProjectOrderListener;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;
use PrikOgStreg\OnlineInvitations\Tests\Support\DeliveryEnvironment;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeBuilderAdapter;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWcOrder;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWcOrderItem;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWcProduct;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class ProjectServiceTest extends TestCase {

	private string $storage_root;

	private FakeWpdb $wpdb;

	private RepositoryRegistry $repositories;

	private ProjectService $service;

	private FakeBuilderAdapter $adapter;

	private int $post_id = 1000;

	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 ) . '/stubs/bpp/Builder_Adapter_Interface.php';

		$this->storage_root = sys_get_temp_dir() . '/pks-oi-project-' . uniqid( '', true );
		$this->wpdb         = new FakeWpdb();
		$this->repositories = new RepositoryRegistry( $this->wpdb );
		$this->adapter      = new FakeBuilderAdapter();

		Functions\when( 'has_filter' )->justReturn( true );
		Functions\when( 'apply_filters' )->justReturn( $this->adapter );
		Functions\when( 'wp_insert_post' )->alias( function (): int {
			++$this->post_id;

			return $this->post_id;
		} );
		Functions\when( 'wp_delete_post' )->justReturn( true );
		Functions\when( 'do_action' )->justReturn( null );

		$builder = new BuilderService();
		$builder->resolve();

		DeliveryEnvironment::bootstrap();
		( new SchedulerRegistrar( $this->repositories ) )->register();

		$welcome = $this->make_welcome_scheduler();
		$this->service = new ProjectService(
			$this->repositories->projects(),
			$this->repositories->events(),
			new ProjectFactory(),
			$builder,
			( new StorageRegistry( $this->storage_root ) )->project_storage(),
			$welcome
		);
	}

	protected function tearDown(): void {
		$this->delete_storage_tree( $this->storage_root );
		parent::tearDown();
	}

	/**
	 * @return list<string>
	 */
	public static function qualifying_status_provider(): array {
		return array_map(
			static fn( string $status ): array => [ $status ],
			ProjectEntitlement::qualifying_statuses()
		);
	}

	/**
	 * @dataProvider qualifying_status_provider
	 */
	public function test_creates_project_for_each_qualifying_status( string $status ): void {
		$order = $this->make_order( $status );
		$this->service->process_order( $order );

		$project = $this->repositories->projects()->find_by_order_item_id( 501 );
		$this->assertIsArray( $project );
		$this->assertSame( ProjectStatus::ACTIVE, $project['status'] );
		$this->assertSame( '1', (string) ( $project['state_version'] ?? '' ) );
	}

	public function test_repeated_transitions_are_idempotent(): void {
		$order = $this->make_order( 'processing' );

		$first  = $this->service->create_from_order_item( $order, $order->get_items( 'line_item' )[501] );
		$second = $this->service->create_from_order_item( $order, $order->get_items( 'line_item' )[501] );

		$this->assertSame( $first, $second );
		$this->assertSame( 1, $this->wpdb->table_count( 'wp_pks_oi_projects' ) );
	}

	public function test_concurrent_lock_prevents_duplicate_rows(): void {
		$order = $this->make_order( 'processing' );
		$item  = $order->get_items( 'line_item' )[501];

		ProjectCreationLock::acquire( 501 );
		$this->assertSame( 0, $this->service->create_from_order_item( $order, $item ) );
		ProjectCreationLock::release( 501 );

		$this->assertGreaterThan( 0, $this->service->create_from_order_item( $order, $item ) );
	}

	public function test_mixed_order_only_creates_invitation_projects(): void {
		$invitation_product = new FakeWcProduct( 10 );
		$simple_product     = new FakeWcProduct( 20, 'simple', 'Simple Product' );

		$order = new FakeWcOrder(
			100,
			7,
			'processing',
			[
				'line_item' => [
					501 => new FakeWcOrderItem( 501, 10, $invitation_product, true ),
					502 => new FakeWcOrderItem( 502, 20, $simple_product, false ),
				],
			]
		);

		$this->service->process_order( $order );

		$this->assertSame( 1, $this->wpdb->table_count( 'wp_pks_oi_projects' ) );
		$this->assertIsArray( $this->repositories->projects()->find_by_order_item_id( 501 ) );
		$this->assertNull( $this->repositories->projects()->find_by_order_item_id( 502 ) );
	}

	public function test_missing_adapter_does_not_create_project(): void {
		$this->adapter->available = false;

		$builder = new BuilderService();
		$builder->resolve();

		$service = new ProjectService(
			$this->repositories->projects(),
			$this->repositories->events(),
			new ProjectFactory(),
			$builder,
			( new StorageRegistry( $this->storage_root ) )->project_storage(),
			new WelcomeScheduler(
				$this->repositories->projects(),
				$this->repositories->deliveries(),
				new DeliveryQueueService( $this->repositories->deliveries() )
			)
		);

		$order = $this->make_order( 'processing' );
		$this->assertSame( 0, $service->create_from_order_item( $order, $order->get_items( 'line_item' )[501] ) );
		$this->assertSame( 0, $this->wpdb->table_count( 'wp_pks_oi_projects' ) );
	}

	public function test_malformed_payload_imports_template_fallback(): void {
		$this->adapter->with_load_state( new class() {
			public function get_error_code(): string {
				return 'malformed_payload';
			}
		} );

		$order = $this->make_order( 'processing' );
		$project_id = $this->service->create_from_order_item( $order, $order->get_items( 'line_item' )[501] );

		$this->assertGreaterThan( 0, $project_id );
		$project = $this->repositories->projects()->find_by_id( $project_id );
		$this->assertTrue( ProjectEntitlement::is_project_usable( $project ) );
		$this->assertSame( ProjectStatus::ACTIVE, $project['status'] ?? '' );
	}

	public function test_existing_project_relinks_order_item_meta(): void {
		$order = $this->make_order( 'processing' );
		$item  = $order->get_items( 'line_item' )[501];

		$this->repositories->projects()->insert(
			[
				'project_id'    => 2001,
				'storage_uuid'  => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
				'user_id'       => 7,
				'order_id'      => 100,
				'order_item_id' => 501,
				'product_id'    => 10,
				'template_id'   => '10',
				'status'        => ProjectStatus::ACTIVE,
				'state_version' => 1,
			]
		);

		$project_id = $this->service->create_from_order_item( $order, $item );
		$this->assertSame( 2001, $project_id );
		$this->assertSame( 2001, (int) $item->get_meta( ProjectMeta::ORDER_ITEM_PROJECT_ID ) );
	}

	public function test_welcome_is_queued_only_once(): void {
		$order = $this->make_order( 'completed' );
		$project_id = $this->service->create_from_order_item( $order, $order->get_items( 'line_item' )[501] );
		$welcome    = $this->make_welcome_scheduler();

		$this->assertTrue( $welcome->handle_scheduled_send( $project_id ) );

		$delivery = $this->repositories->deliveries()->find_by_idempotency_key( 'welcome:' . $project_id );
		$this->assertIsArray( $delivery );
		$this->assertSame( 'sent', $delivery['status'] ?? '' );
		$this->assertSame( 1, $this->wpdb->table_count( 'wp_pks_oi_deliveries' ) );
		$this->assertFalse( $welcome->queue_once( $project_id ) );
		$this->assertTrue( $welcome->was_welcome_sent( $project_id ) );
		$this->assertSame( 1, $this->wpdb->table_count( 'wp_pks_oi_deliveries' ) );
	}

	public function test_file_failure_leaves_retryable_project_row(): void {
		$this->adapter->with_load_state(
			[
				'field'          => [ 'uuid-1' => [ 'text' => 'Hello' ] ],
				'page'           => [ "\xC3\x28" ],
				'size'           => 'a5',
				'format'         => 'flat',
				'schema_version' => '1',
			]
		);

		$order = $this->make_order( 'processing' );
		$project_id = $this->service->create_from_order_item( $order, $order->get_items( 'line_item' )[501] );

		$this->assertGreaterThan( 0, $project_id );
		$project = $this->repositories->projects()->find_by_id( $project_id );
		$this->assertSame( ProjectStatus::DRAFT, $project['status'] ?? '' );
		$this->assertNotSame( '', (string) ( $project['last_error_code'] ?? '' ) );
	}

	public function test_retry_import_succeeds_after_failure(): void {
		$this->adapter->with_load_state( new class() {
			public function get_error_code(): string {
				return 'temporary_failure';
			}
		} );

		$order = $this->make_order( 'processing' );
		$project_id = $this->service->create_from_order_item( $order, $order->get_items( 'line_item' )[501] );
		$this->assertGreaterThan( 0, $project_id );

		$this->adapter->with_load_state( null );
		Functions\when( 'wc_get_order' )->justReturn( $order );

		$this->assertTrue( $this->service->retry_import( $project_id ) );
		$project = $this->repositories->projects()->find_by_id( $project_id );
		$this->assertSame( ProjectStatus::ACTIVE, $project['status'] ?? '' );
		$this->assertSame( '', (string) ( $project['last_error_code'] ?? '' ) );
	}

	public function test_order_listener_delegates_to_service(): void {
		$order = $this->make_order( 'on-hold' );
		Functions\when( 'wc_get_order' )->justReturn( $order );

		$listener = new ProjectOrderListener( $this->service );
		$listener->handle_qualifying_status( 100, $order );

		$this->assertIsArray( $this->repositories->projects()->find_by_order_item_id( 501 ) );
	}

	private function make_welcome_scheduler(): WelcomeScheduler {
		return new WelcomeScheduler(
			$this->repositories->projects(),
			$this->repositories->deliveries(),
			new DeliveryQueueService( $this->repositories->deliveries() )
		);
	}

	private function make_order( string $status ): FakeWcOrder {
		$product = new FakeWcProduct( 10 );

		return new FakeWcOrder(
			100,
			7,
			$status,
			[
				'line_item' => [
					501 => new FakeWcOrderItem( 501, 10, $product, true ),
				],
			]
		);
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
