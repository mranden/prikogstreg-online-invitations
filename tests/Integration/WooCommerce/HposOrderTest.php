<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\WooCommerce;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectMeta;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectFactory;
use PrikOgStreg\OnlineInvitations\Scheduling\WelcomeScheduler;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Storage\StorageRegistry;
use PrikOgStreg\OnlineInvitations\Tests\Support\DeliveryEnvironment;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeBuilderAdapter;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWcOrder;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWcOrderItem;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWcProduct;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

/**
 * HPOS-safe smoke: orders are always accessed through wc_get_order().
 */
final class HposOrderTest extends TestCase {

	private string $storage_root;

	private FakeWpdb $wpdb;

	private RepositoryRegistry $repositories;

	private ProjectService $service;

	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 ) . '/stubs/bpp/Builder_Adapter_Interface.php';

		$this->storage_root = sys_get_temp_dir() . '/pks-oi-hpos-' . uniqid( '', true );
		$this->wpdb         = new FakeWpdb();
		$this->repositories = new RepositoryRegistry( $this->wpdb );
		$adapter            = new FakeBuilderAdapter();

		Functions\when( 'has_filter' )->justReturn( true );
		Functions\when( 'apply_filters' )->justReturn( $adapter );
		Functions\when( 'wp_insert_post' )->justReturn( 2001 );
		Functions\when( 'do_action' )->justReturn( null );

		$builder = new BuilderService();
		$builder->resolve();
		DeliveryEnvironment::bootstrap();

		$this->service = new ProjectService(
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
	}

	protected function tearDown(): void {
		$this->delete_tree( $this->storage_root );
		parent::tearDown();
	}

	public function test_wc_get_order_creates_project_and_persists_item_meta(): void {
		$product = new FakeWcProduct( 10 );
		$item    = new FakeWcOrderItem( 901, 10, $product, true );
		$order   = new FakeWcOrder( 500, 7, 'processing', [ 'line_item' => [ 901 => $item ] ] );

		Functions\when( 'wc_get_order' )->justReturn( $order );

		$resolved = wc_get_order( 500 );
		$this->assertSame( $order, $resolved );

		$project_id = $this->service->create_from_order_item( $order, $item );
		$this->assertGreaterThan( 0, $project_id );
		$this->assertSame( $project_id, (int) $item->get_meta( ProjectMeta::ORDER_ITEM_PROJECT_ID ) );

		$project = $this->repositories->projects()->find_by_order_item_id( 901 );
		$this->assertIsArray( $project );
		$this->assertSame( 500, (int) ( $project['order_id'] ?? 0 ) );
	}

	private function delete_tree( string $root ): void {
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
