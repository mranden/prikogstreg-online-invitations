<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Privacy;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Database\ProjectDomainCleanup;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryStatus;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryType;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestTokenService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoServiceFactory;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectArchiveService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectHardDeleteService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectLifecycleAudit;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;
use PrikOgStreg\OnlineInvitations\Privacy\GuestAnonymizer;
use PrikOgStreg\OnlineInvitations\Privacy\PersonalDataEraser;
use PrikOgStreg\OnlineInvitations\Privacy\PersonalDataExporter;
use PrikOgStreg\OnlineInvitations\Privacy\RetentionPolicy;
use PrikOgStreg\OnlineInvitations\Public\TokenResolver;
use PrikOgStreg\OnlineInvitations\Scheduling\RetentionScheduler;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;
use PrikOgStreg\OnlineInvitations\Storage\StorageCleanup;
use PrikOgStreg\OnlineInvitations\Storage\StorageRegistry;
use PrikOgStreg\OnlineInvitations\Tests\Support\DeliveryEnvironment;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class PrivacyTest extends TestCase {

	private FakeWpdb $wpdb;

	private RepositoryRegistry $repositories;

	private StorageRegistry $storage;

	private string $storage_root;

	protected function setUp(): void {
		parent::setUp();

		$this->wpdb         = new FakeWpdb();
		$this->repositories = new RepositoryRegistry( $this->wpdb );
		$this->storage_root = sys_get_temp_dir() . '/pks-oi-privacy-' . uniqid( '', true );
		$this->storage      = new StorageRegistry( $this->storage_root );

		DeliveryEnvironment::bootstrap();
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		Functions\when( 'get_user_by' )->justReturn( false );
		$this->register_domain_cleanup();
	}

	private function register_domain_cleanup(): void {
		$cleanup = new ProjectDomainCleanup(
			$this->repositories->projects(),
			$this->repositories->guests(),
			$this->repositories->wishlist_items(),
			$this->repositories->wishlist_reservations(),
			$this->repositories->photos(),
			$this->repositories->deliveries(),
			$this->repositories->events(),
			$this->storage->project_storage()
		);

		Functions\when( 'wp_delete_post' )->alias(
			function ( int $post_id, bool $force = false ) use ( $cleanup ): int {
				unset( $force );
				$cleanup->cleanup( $post_id );

				return $post_id;
			}
		);
	}

	protected function tearDown(): void {
		$this->delete_tree( $this->storage_root );
		parent::tearDown();
	}

	public function test_exporter_includes_owner_projects_not_other_owners(): void {
		$this->seed_project( 7001, 7, 'owner7@example.com' );
		$this->seed_project( 7002, 9, 'other@example.com' );

		Functions\when( 'get_user_by' )->justReturn( (object) [ 'ID' => 7 ] );

		$exporter = $this->make_exporter();
		$items    = $exporter->export_for_email( 'owner7@example.com' );

		$project_ids = [];
		foreach ( $items as $item ) {
			if ( 'pks-oi-projects' === ( $item['group_id'] ?? '' ) ) {
				$project_ids[] = (int) str_replace( 'project-', '', (string) ( $item['item_id'] ?? '' ) );
			}
		}

		$this->assertSame( [ 7001 ], $project_ids );
	}

	public function test_exporter_includes_address_book_for_owner(): void {
		$this->seed_project( 7001, 7, 'owner7@example.com' );
		$this->repositories->address_book()->insert(
			[
				'user_id'               => 7,
				'display_name'          => 'Alice',
				'email'                 => 'alice@example.com',
				'normalized_email_hash' => hash( 'sha256', 'alice@example.com' ),
			]
		);

		Functions\when( 'get_user_by' )->justReturn( (object) [ 'ID' => 7 ] );

		$items = $this->make_exporter()->export_for_email( 'owner7@example.com' );
		$groups = array_column( $items, 'group_id' );

		$this->assertContains( 'pks-oi-address-book', $groups );
	}

	public function test_exporter_guest_scope_excludes_other_guests(): void {
		$this->seed_project( 7001, 9, 'owner@example.com' );
		$this->seed_guest( 7001, 'guest@example.com', 'Guest One' );
		$this->seed_guest( 7001, 'otherguest@example.com', 'Guest Two' );

		$items = $this->make_exporter()->export_for_email( 'guest@example.com' );
		$names = [];
		foreach ( $items as $item ) {
			if ( 'pks-oi-guest-self' !== ( $item['group_id'] ?? '' ) ) {
				continue;
			}
			foreach ( $item['data'] as $field ) {
				if ( 'display_name' === ( $field['name'] ?? '' ) ) {
					$names[] = (string) ( $field['value'] ?? '' );
				}
			}
		}

		$this->assertSame( [ 'Guest One' ], $names );
		$this->assertNotContains( 'pks-oi-projects', array_column( $items, 'group_id' ) );
	}

	public function test_eraser_anonymizes_guest_and_invalidates_token(): void {
		$token = InvitationToken::generate();
		$this->seed_project( 7001, 9, 'owner@example.com' );
		$this->repositories->guests()->insert(
			[
				'guest_id'     => 501,
				'project_id'   => 7001,
				'display_name' => 'Guest',
				'email'        => 'guest@example.com',
				'token_hash'   => $token['hash'],
			]
		);

		$eraser = $this->make_eraser();
		$result = $eraser->erase_for_email( 'guest@example.com' );

		$this->assertTrue( $result['items_removed'] );
		$guest = $this->repositories->guests()->find_by_id( 501 );
		$this->assertIsArray( $guest );
		$this->assertSame( RetentionPolicy::ERASED_GUEST_LABEL, $guest['display_name'] ?? '' );
		$this->assertSame( '', $guest['email'] ?? '' );

		$resolver = new TokenResolver( $this->repositories->guests(), $this->repositories->projects() );
		$this->assertNull( $resolver->resolve( $token['raw'] ) );
	}

	public function test_eraser_reports_retained_commerce_records(): void {
		$this->seed_project( 7001, 7, 'owner7@example.com', 100 );
		Functions\when( 'get_user_by' )->justReturn( (object) [ 'ID' => 7 ] );

		$result = $this->make_eraser()->erase_for_email( 'owner7@example.com' );

		$this->assertTrue( $result['items_retained'] );
		$this->assertStringContainsString( 'order', strtolower( implode( ' ', $result['messages'] ) ) );
	}

	public function test_hard_delete_is_idempotent_and_cancels_jobs(): void {
		$project = $this->seed_project( 7001, 7, 'owner7@example.com' );
		$delivery_id = $this->repositories->deliveries()->insert(
			[
				'project_id'      => 7001,
				'delivery_type'   => DeliveryType::WELCOME,
				'idempotency_key' => 'welcome:7001',
				'status'          => DeliveryStatus::QUEUED,
			]
		);

		$service = $this->make_hard_delete();
		$first   = $service->delete( $project, 'test' );
		$this->assertTrue( $first->success );
		$this->assertNull( $this->repositories->projects()->find_by_id( 7001 ) );

		$second = $service->delete( $project, 'test' );
		$this->assertTrue( $second->done );

		$row = $this->repositories->deliveries()->find_by_id( $delivery_id );
		$this->assertNull( $row );
	}

	public function test_hard_delete_retry_after_partial_storage_failure(): void {
		$uuid    = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
		$project = $this->seed_project( 7001, 7, 'owner7@example.com', 0, $uuid );
		$root    = $this->storage->paths()->project_root( $uuid );
		mkdir( $root . '/tmp', 0777, true );
		file_put_contents( $root . '/tmp/stale.txt', 'x' );

		$service = $this->make_hard_delete();
		$result  = $service->delete( $project, 'retry-test' );
		$this->assertNull( $this->repositories->projects()->find_by_id( 7001 ) );
		$this->assertTrue( $result->success || $result->done );

		$retry = $service->delete( $project, 'retry-test' );
		$this->assertTrue( $retry->done );
	}

	public function test_archive_cancels_queued_deliveries(): void {
		$project = $this->seed_project( 7001, 7, 'owner7@example.com' );
		$delivery_id = $this->repositories->deliveries()->insert(
			[
				'project_id'      => 7001,
				'delivery_type'   => DeliveryType::RSVP_REMINDER,
				'idempotency_key' => 'reminder:7001:1:2026-07-01',
				'status'          => DeliveryStatus::QUEUED,
			]
		);

		$archive = new ProjectArchiveService(
			$this->repositories->projects(),
			new DeliveryQueueService( $this->repositories->deliveries() ),
			new ProjectLifecycleAudit( $this->repositories->events() )
		);

		$this->assertTrue( $archive->archive( $project, 'test' ) );
		$row = $this->repositories->projects()->find_by_id( 7001 );
		$this->assertSame( ProjectStatus::ARCHIVED, $row['status'] ?? '' );
		$this->assertSame( PublicationStatus::UNPUBLISHED, $row['publication_status'] ?? '' );

		$delivery = $this->repositories->deliveries()->find_by_id( $delivery_id );
		$this->assertSame( DeliveryStatus::CANCELLED, $delivery['status'] ?? '' );
	}

	public function test_retention_scheduler_prunes_old_logs(): void {
		$this->seed_project( 7001, 7, 'owner7@example.com' );
		$this->repositories->events()->insert(
			[
				'project_id'    => 7001,
				'event_type'    => 'test.old',
				'metadata_json' => '{}',
				'created_at_utc'=> gmdate( 'Y-m-d H:i:s', time() - ( 400 * DAY_IN_SECONDS ) ),
			]
		);
		$delivery_id = $this->repositories->deliveries()->insert(
			[
				'project_id'       => 7001,
				'delivery_type'    => DeliveryType::WELCOME,
				'idempotency_key'  => 'welcome-old',
				'recipient_hash'   => hash( 'sha256', 'owner7@example.com' ),
				'status'           => DeliveryStatus::SENT,
				'last_error_message' => 'smtp timeout',
				'created_at_utc'   => gmdate( 'Y-m-d H:i:s', time() - ( 800 * DAY_IN_SECONDS ) ),
			]
		);

		$scheduler = new RetentionScheduler(
			$this->repositories->projects(),
			$this->repositories->deliveries(),
			$this->repositories->events(),
			new StorageCleanup( $this->storage->paths() )
		);

		$scheduler->prune_event_logs();
		$scheduler->prune_delivery_logs();

		$this->assertSame( 0, $this->wpdb->table_count( 'wp_pks_oi_events' ) );
		$delivery = $this->repositories->deliveries()->find_by_id( $delivery_id );
		$this->assertIsArray( $delivery );
		$this->assertSame( '', (string) ( $delivery['recipient_hash'] ?? '' ) );
		$this->assertSame( '', (string) ( $delivery['last_error_message'] ?? '' ) );
	}

	public function test_uninstall_preserves_data_by_default(): void {
		$uninstall = file_get_contents( dirname( __DIR__, 3 ) . '/uninstall.php' );
		$this->assertIsString( $uninstall );
		$this->assertStringContainsString( 'PKS_OI_UNINSTALL_DELETE_DATA', $uninstall );
		$this->assertStringContainsString( 'preserves', strtolower( $uninstall ) );
	}

	private function make_exporter(): PersonalDataExporter {
		return new PersonalDataExporter(
			$this->repositories->projects(),
			$this->repositories->guests(),
			$this->repositories->address_book(),
			$this->repositories->wishlist_items(),
			$this->repositories->wishlist_reservations(),
			$this->repositories->photos(),
			$this->repositories->deliveries(),
			$this->repositories->events()
		);
	}

	private function make_eraser(): PersonalDataEraser {
		return new PersonalDataEraser(
			$this->repositories->projects(),
			$this->repositories->guests(),
			$this->repositories->address_book(),
			new GuestAnonymizer(
				$this->repositories->guests(),
				new GuestTokenService( $this->repositories->guests() ),
				PhotoServiceFactory::create( $this->repositories, $this->storage )
			),
			$this->make_hard_delete()
		);
	}

	private function make_hard_delete(): ProjectHardDeleteService {
		return new ProjectHardDeleteService(
			$this->repositories->projects(),
			new DeliveryQueueService( $this->repositories->deliveries() ),
			new ProjectLifecycleAudit( $this->repositories->events() ),
			$this->storage->project_storage()
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function seed_project(
		int $project_id,
		int $user_id,
		string $owner_email,
		int $order_id = 0,
		string $storage_uuid = ''
	): array {
		if ( '' === $storage_uuid ) {
			$storage_uuid = sprintf( '%08x-bbbb-4ccc-8ddd-%012x', $project_id, $project_id );
		}
		$this->repositories->projects()->insert(
			[
				'project_id'         => $project_id,
				'storage_uuid'       => $storage_uuid,
				'user_id'            => $user_id,
				'order_id'           => $order_id,
				'order_item_id'      => $project_id + 100,
				'product_id'         => 10,
				'template_id'        => '10',
				'status'             => ProjectStatus::ACTIVE,
				'publication_status' => PublicationStatus::PUBLISHED,
				'public_contact_email' => $owner_email,
				'state_version'      => 1,
			]
		);

		$project = $this->repositories->projects()->find_by_id( $project_id );
		$this->assertIsArray( $project );

		return $project;
	}

	private function seed_guest( int $project_id, string $email, string $name ): void {
		$pair = InvitationToken::generate();
		$this->repositories->guests()->insert(
			[
				'project_id'   => $project_id,
				'display_name' => $name,
				'email'        => $email,
				'token_hash'   => $pair['hash'],
			]
		);
	}

	private function delete_tree( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		foreach ( scandir( $directory ) ?: [] as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $directory . '/' . $item;
			if ( is_dir( $path ) ) {
				$this->delete_tree( $path );
				continue;
			}
			@unlink( $path );
		}
		@rmdir( $directory );
	}
}
