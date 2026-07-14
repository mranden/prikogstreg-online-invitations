<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Project;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Domain\Project\EnvelopeSnapshot;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectDesignSource;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectEntitlement;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectFactory;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectPreviewService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStateService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Scheduling\WelcomeScheduler;
use PrikOgStreg\OnlineInvitations\Storage\EnvelopeManifest;
use PrikOgStreg\OnlineInvitations\Storage\StorageRegistry;
use PrikOgStreg\OnlineInvitations\WooCommerce\Cart\CartPayload;
use PrikOgStreg\OnlineInvitations\WooCommerce\Cart\CartPayloadValidator;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\EnvelopeDesign;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;
use PrikOgStreg\OnlineInvitations\Tests\Support\DeliveryEnvironment;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeBuilderAdapter;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWcOrder;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWcOrderItem;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWcProduct;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class ProjectImportSnapshotTest extends TestCase {

	private string $storage_root;

	private FakeWpdb $wpdb;

	private RepositoryRegistry $repositories;

	private ProjectService $service;

	private FakeBuilderAdapter $adapter;

	private int $post_id = 2000;

	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 ) . '/stubs/bpp/Builder_Adapter_Interface.php';

		$this->storage_root = sys_get_temp_dir() . '/pks-oi-import-' . uniqid( '', true );
		$this->wpdb         = new FakeWpdb();
		$this->repositories = new RepositoryRegistry( $this->wpdb );
		$this->adapter      = new FakeBuilderAdapter();

		Functions\when( 'has_filter' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias(
			function ( string $hook, $value, ...$args ) {
				if ( 'bpp/integration/service' === $hook ) {
					return $this->adapter;
				}

				if ( EnvelopeDesign::FILTER === $hook ) {
					return is_array( $value ) ? $value : [];
				}

				return $value;
			}
		);
		Functions\when( 'wp_insert_post' )->alias( function (): int {
			++$this->post_id;

			return $this->post_id;
		} );
		Functions\when( 'wp_delete_post' )->justReturn( true );
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'get_post_type' )->justReturn( 'attachment' );
		Functions\when( 'wp_attachment_is_image' )->justReturn( true );

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
		$this->delete_storage_tree( $this->storage_root );
		parent::tearDown();
	}

	public function test_import_writes_builder_and_envelope_snapshot_from_fixture(): void {
		$payload = $this->load_fixture( 'builder-order-payload.json' );
		$this->adapter->with_load_state( $payload );

		$order      = $this->make_order(
			'processing',
			[],
			new FakeWcProduct(
				10,
				ProductMeta::TYPE,
				'Invitation Product',
				[
					ProductMeta::ENVELOPE_PRESET   => 'classic',
					ProductMeta::BACKGROUND_PRESET => 'neutral',
				]
			)
		);
		$project_id = $this->service->create_from_order_item( $order, $order->get_items( 'line_item' )[501] );

		$this->assertGreaterThan( 0, $project_id );
		$project = $this->repositories->projects()->find_by_id( $project_id );
		$this->assertIsArray( $project );
		$this->assertSame( ProjectStatus::ACTIVE, $project['status'] ?? '' );
		$this->assertSame( 1, (int) ( $project['state_version'] ?? 0 ) );

		$storage = ( new StorageRegistry( $this->storage_root ) )->project_storage();
		$manifest = $storage->read_state_manifest( (string) $project['storage_uuid'] );
		$this->assertCount( 1, $manifest->pages );

		$envelope = $storage->read_envelope_manifest( (string) $project['storage_uuid'] );
		$this->assertSame( 'classic', $envelope->preset );
		$this->assertSame( 'neutral', $envelope->background_preset );
		$this->assertSame( 'preset_only', $envelope->configuration_type );
		$this->assertSame( EnvelopeManifest::MEDIA_NONE, $envelope->media_storage );

		$state_json = $storage->read_current_state( (string) $project['storage_uuid'] );
		$state      = json_decode( $state_json, true );
		$this->assertIsArray( $state );
		$this->assertSame( 1, (int) ( $state['page_count'] ?? 0 ) );
		$this->assertSame( 'a5', $state['size'] ?? '' );
		$this->assertSame( 'flat', $state['format'] ?? '' );
	}

	public function test_import_writes_multi_page_payload(): void {
		$payload = $this->load_fixture( 'builder-order-payload-multi-page.json' );
		$this->adapter->with_load_state( $payload );

		$order      = $this->make_order( 'processing' );
		$project_id = $this->service->create_from_order_item( $order, $order->get_items( 'line_item' )[501] );
		$project    = $this->repositories->projects()->find_by_id( $project_id );
		$this->assertIsArray( $project );

		$storage  = ( new StorageRegistry( $this->storage_root ) )->project_storage();
		$manifest = $storage->read_state_manifest( (string) $project['storage_uuid'] );
		$this->assertCount( 2, $manifest->pages );

		$state = json_decode( $storage->read_current_state( (string) $project['storage_uuid'] ), true );
		$this->assertIsArray( $state );
		$this->assertSame( 2, (int) ( $state['page_count'] ?? 0 ) );
		$this->assertCount( 2, $state['thumbnails'] ?? [] );
	}

	public function test_envelope_snapshot_is_independent_of_later_product_changes(): void {
		$payload = $this->load_fixture( 'builder-order-payload.json' );
		$this->adapter->with_load_state( $payload );

		$product = new FakeWcProduct(
			10,
			ProductMeta::TYPE,
			'Invitation Product',
			[
				ProductMeta::ENVELOPE_PRESET   => 'classic',
				ProductMeta::BACKGROUND_PRESET => 'floral',
			]
		);
		$order      = $this->make_order( 'processing', [], $product );
		$project_id = $this->service->create_from_order_item( $order, $order->get_items( 'line_item' )[501] );
		$project    = $this->repositories->projects()->find_by_id( $project_id );
		$this->assertIsArray( $project );
		$this->assertSame( 'floral', $project['background_preset'] ?? '' );

		$this->repositories->projects()->update(
			$project_id,
			[
				'envelope_preset'   => 'modern',
				'background_preset' => 'geometric',
			]
		);

		$storage  = ( new StorageRegistry( $this->storage_root ) )->project_storage();
		$envelope = $storage->read_envelope_manifest( (string) $project['storage_uuid'] );
		$this->assertSame( 'classic', $envelope->preset );
		$this->assertSame( 'floral', $envelope->background_preset );
	}

	public function test_empty_page_payload_imports_template_fallback(): void {
		$this->adapter->with_load_state(
			[
				'field'          => [ 'uuid-1' => [ 'text' => 'Hello' ] ],
				'page'           => [],
				'size'           => 'a5',
				'format'         => 'flat',
				'schema_version' => '1',
			]
		);

		$order      = $this->make_order( 'processing' );
		$project_id = $this->service->create_from_order_item( $order, $order->get_items( 'line_item' )[501] );
		$project    = $this->repositories->projects()->find_by_id( $project_id );

		$this->assertIsArray( $project );
		$this->assertSame( '', (string) ( $project['last_error_code'] ?? '' ) );
		$this->assertSame( ProjectStatus::ACTIVE, $project['status'] ?? '' );
		$this->assertTrue( ProjectEntitlement::is_project_usable( $project ) );

		$storage    = ( new StorageRegistry( $this->storage_root ) )->project_storage();
		$state_json = $storage->read_current_state( (string) $project['storage_uuid'] );
		$state      = json_decode( $state_json, true );
		$this->assertIsArray( $state );
		$this->assertSame( ProjectDesignSource::TEMPLATE_FALLBACK, $state['design_source'] ?? '' );

		$builder = new BuilderService();
		$builder->resolve();

		$preview_service = new ProjectPreviewService(
			$builder,
			new ProjectStateService(
				$builder,
				$storage,
				$this->repositories->projects(),
				$this->repositories->events()
			)
		);

		$preview = $preview_service->render_preview( $project );
		$this->assertStringContainsString( 'Preview page', $preview['html'] );
	}

	public function test_checksum_mismatch_records_retryable_failure(): void {
		$payload = $this->load_fixture( 'builder-order-payload.json' );
		$this->adapter->with_load_state( $payload );

		$order = $this->make_order( 'processing' );
		$item  = $order->get_items( 'line_item' )[501];
		$item->update_meta_data( CartPayload::ORDER_META_CHECKSUM, 'deadbeef' );

		$project_id = $this->service->create_from_order_item( $order, $item );
		$project    = $this->repositories->projects()->find_by_id( $project_id );

		$this->assertIsArray( $project );
		$this->assertSame( 'checksum_mismatch', $project['last_error_code'] ?? '' );
	}

	public function test_repeated_import_is_idempotent(): void {
		$payload = $this->load_fixture( 'builder-order-payload.json' );
		$this->adapter->with_load_state( $payload );

		$order = $this->make_order( 'processing' );
		$item  = $order->get_items( 'line_item' )[501];

		$first  = $this->service->create_from_order_item( $order, $item );
		$second = $this->service->create_from_order_item( $order, $item );

		$this->assertSame( $first, $second );
		$this->assertSame( 1, $this->wpdb->table_count( 'wp_pks_oi_projects' ) );

		$project = $this->repositories->projects()->find_by_id( $first );
		$this->assertIsArray( $project );
		$storage_uuid = (string) $project['storage_uuid'];

		$storage  = ( new StorageRegistry( $this->storage_root ) )->project_storage();
		$manifest = $storage->read_state_manifest( $storage_uuid );
		$this->assertSame( 1, $manifest->state_version );
	}

	public function test_adapter_unavailable_after_successful_import_does_not_break_project(): void {
		$payload = $this->load_fixture( 'builder-order-payload.json' );
		$this->adapter->with_load_state( $payload );

		$order      = $this->make_order( 'processing' );
		$project_id = $this->service->create_from_order_item( $order, $order->get_items( 'line_item' )[501] );
		$project    = $this->repositories->projects()->find_by_id( $project_id );
		$this->assertIsArray( $project );
		$this->assertTrue( ProjectEntitlement::is_project_usable( $project ) );

		$this->adapter->available = false;
		$builder = new BuilderService();
		$builder->resolve();

		$this->assertTrue( $this->service->retry_import( $project_id ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_envelope_image_copy_writes_project_owned_file_when_attachment_readable(): void {
		$payload = $this->load_fixture( 'builder-order-payload.json' );
		$this->adapter->with_load_state( $payload );

		$order = $this->make_order(
			'processing',
			[],
			new FakeWcProduct(
				10,
				ProductMeta::TYPE,
				'Invitation Product',
				[
					ProductMeta::ENVELOPE_PRESET   => 'classic',
					ProductMeta::BACKGROUND_PRESET => 'neutral',
					ProductMeta::ENVELOPE_IMAGE_ID => '88',
				]
			)
		);

		$project_id = $this->service->create_from_order_item( $order, $order->get_items( 'line_item' )[501] );
		$project    = $this->repositories->projects()->find_by_id( $project_id );
		$this->assertIsArray( $project );
		$this->assertSame( 88, (int) ( $project['envelope_image_id'] ?? 0 ) );

		$storage  = ( new StorageRegistry( $this->storage_root ) )->project_storage();
		$envelope = $storage->read_envelope_manifest( (string) $project['storage_uuid'] );
		$this->assertSame( 88, $envelope->attachment_id );
		$this->assertContains(
			$envelope->media_storage,
			[ EnvelopeManifest::MEDIA_PROJECT_COPY, EnvelopeManifest::MEDIA_ATTACHMENT ],
			'Envelope media should be snapshotted as project copy or attachment reference.'
		);
	}

	public function test_malformed_payload_from_adapter_imports_template_fallback_without_duplicate_projects(): void {
		$this->adapter->with_load_state( new class() {
			public function get_error_code(): string {
				return 'malformed_payload';
			}
		} );

		$order = $this->make_order( 'processing' );
		$item  = $order->get_items( 'line_item' )[501];

		$first  = $this->service->create_from_order_item( $order, $item );
		$second = $this->service->create_from_order_item( $order, $item );

		$this->assertSame( $first, $second );
		$this->assertSame( 1, $this->wpdb->table_count( 'wp_pks_oi_projects' ) );

		$project = $this->repositories->projects()->find_by_id( $first );
		$this->assertIsArray( $project );
		$this->assertTrue( ProjectEntitlement::is_project_usable( $project ) );
	}

	/**
	 * @param array<string, mixed> $overrides
	 */
	private function make_order( string $status, array $overrides = [], ?object $product = null ): FakeWcOrder {
		$product = $product ?? new FakeWcProduct( 10 );

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

	/**
	 * @return array<string, mixed>
	 */
	private function load_fixture( string $filename ): array {
		$path = dirname( __DIR__, 2 ) . '/Fixtures/' . $filename;
		$raw  = file_get_contents( $path );
		$this->assertIsString( $raw );

		$data = json_decode( $raw, true );
		$this->assertIsArray( $data );

		return $data;
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
