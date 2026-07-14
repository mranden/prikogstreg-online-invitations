<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Lifecycle;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectExpiration;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectExpireService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectLifecycleAudit;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectRestoreService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectRestrictionService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicEntitlement;
use PrikOgStreg\OnlineInvitations\Public\TokenResolution;
use PrikOgStreg\OnlineInvitations\Public\TokenResolver;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;
use PrikOgStreg\OnlineInvitations\WooCommerce\Orders\OrderRefundDetector;
use PrikOgStreg\OnlineInvitations\WooCommerce\Orders\ProjectRefundListener;

final class LifecycleTest extends TestCase {

	private FakeWpdb $wpdb;

	private RepositoryRegistry $repositories;

	protected function setUp(): void {
		parent::setUp();
		$this->wpdb         = new FakeWpdb();
		$this->repositories = new RepositoryRegistry( $this->wpdb );
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'get_current_user_id' )->justReturn( 1 );
	}

	public function test_effective_expiry_from_event_end_plus_90_days(): void {
		$project = [
			'event_end_utc'   => '2026-06-01 18:00:00',
			'event_start_utc' => '2026-06-01 10:00:00',
		];

		$this->assertSame( '2026-08-30 18:00:00', ProjectExpiration::effective_expiry( $project ) );
	}

	public function test_expiry_override_takes_precedence(): void {
		$project = [
			'expiry_override_utc' => '2027-01-01 00:00:00',
			'event_end_utc'       => '2026-06-01 18:00:00',
		];

		$this->assertSame( '2027-01-01 00:00:00', ProjectExpiration::effective_expiry( $project ) );
	}

	public function test_restrict_makes_public_unavailable_and_idempotent(): void {
		$project = $this->seed_project();
		$queue   = new DeliveryQueueService( $this->repositories->deliveries() );
		$audit   = new ProjectLifecycleAudit( $this->repositories->events() );
		$service = new ProjectRestrictionService( $this->repositories->projects(), $queue, $audit );

		$this->assertTrue( $service->restrict( $project, 'test', 'admin' ) );
		$updated = $this->repositories->projects()->find_by_id( (int) $project['project_id'] );
		$this->assertIsArray( $updated );
		$this->assertSame( ProjectStatus::RESTRICTED, $updated['status'] );
		$this->assertSame( PublicationStatus::UNPUBLISHED, $updated['publication_status'] );
		$this->assertFalse( PublicEntitlement::is_publicly_available( $updated ) );

		$events_before = count( $this->repositories->events()->list_recent_for_project( (int) $project['project_id'], 50 ) );
		$this->assertTrue( $service->restrict( $updated, 'again', 'admin' ) );
		$events_after = count( $this->repositories->events()->list_recent_for_project( (int) $project['project_id'], 50 ) );
		$this->assertSame( $events_before, $events_after );
	}

	public function test_expire_marks_expired_without_hard_delete(): void {
		$project = $this->seed_project(
			[
				'expires_at_utc' => gmdate( 'Y-m-d H:i:s', time() - 3600 ),
			]
		);
		$expire = new ProjectExpireService(
			$this->repositories->projects(),
			new DeliveryQueueService( $this->repositories->deliveries() ),
			new ProjectLifecycleAudit( $this->repositories->events() )
		);

		$this->assertTrue( $expire->expire_project( $project ) );
		$row = $this->repositories->projects()->find_by_id( (int) $project['project_id'] );
		$this->assertIsArray( $row );
		$this->assertSame( ProjectStatus::EXPIRED, $row['status'] );
		$this->assertNotNull( $row['expired_at_utc'] ?? null );
		$this->assertFalse( PublicEntitlement::is_publicly_available( $row ) );
	}

	public function test_restore_requires_not_refunded(): void {
		$project = $this->seed_project( [ 'status' => ProjectStatus::RESTRICTED ] );
		$restore = new ProjectRestoreService(
			$this->repositories->projects(),
			new OrderRefundDetector(),
			new ProjectLifecycleAudit( $this->repositories->events() )
		);

		$result = $restore->restore( $project, 'manual restore' );
		$this->assertTrue( $result['success'] );
		$row = $this->repositories->projects()->find_by_id( (int) $project['project_id'] );
		$this->assertSame( ProjectStatus::ACTIVE, $row['status'] ?? '' );
	}

	public function test_full_line_refund_restricts_project(): void {
		$project = $this->seed_project();
		$order   = new FakeWcOrder( 100, [ (int) $project['order_item_id'] => 1 ], true );
		Functions\when( 'wc_get_order' )->justReturn( $order );

		$listener = new ProjectRefundListener(
			$this->repositories->projects(),
			new OrderRefundDetector(),
			new ProjectRestrictionService(
				$this->repositories->projects(),
				new DeliveryQueueService( $this->repositories->deliveries() ),
				new ProjectLifecycleAudit( $this->repositories->events() )
			)
		);

		$listener->handle_order_refunded( 100, 9001 );
		$row = $this->repositories->projects()->find_by_id( (int) $project['project_id'] );
		$this->assertSame( ProjectStatus::RESTRICTED, $row['status'] ?? '' );
	}

	public function test_partial_unrelated_refund_does_not_restrict(): void {
		$project = $this->seed_project();
		$order   = new FakeWcOrder( 100, [ (int) $project['order_item_id'] => 1 ], false );
		Functions\when( 'wc_get_order' )->justReturn( $order );

		$listener = new ProjectRefundListener(
			$this->repositories->projects(),
			new OrderRefundDetector(),
			new ProjectRestrictionService(
				$this->repositories->projects(),
				new DeliveryQueueService( $this->repositories->deliveries() ),
				new ProjectLifecycleAudit( $this->repositories->events() )
			)
		);

		$listener->handle_order_refunded( 100, 9001 );
		$row = $this->repositories->projects()->find_by_id( (int) $project['project_id'] );
		$this->assertSame( ProjectStatus::ACTIVE, $row['status'] ?? '' );
	}

	public function test_restricted_project_blocks_rsvp_context(): void {
		$project = $this->seed_project(
			[
				'status'             => ProjectStatus::RESTRICTED,
				'publication_status' => PublicationStatus::UNPUBLISHED,
				'restricted_at_utc'  => gmdate( 'Y-m-d H:i:s' ),
			]
		);
		$token   = InvitationToken::generate();
		$this->repositories->guests()->insert(
			[
				'project_id'   => (int) $project['project_id'],
				'display_name' => 'Guest',
				'token_hash'   => $token['hash'],
			]
		);
		$resolver = new TokenResolver( $this->repositories->guests(), $this->repositories->projects() );
		$resolution = $resolver->resolve( $token['raw'] );
		$this->assertInstanceOf( TokenResolution::class, $resolution );
		$this->assertFalse( PublicEntitlement::is_publicly_available( $resolution->project() ) );
	}

	/**
	 * @param array<string, mixed> $overrides
	 * @return array<string, mixed>
	 */
	private function seed_project( array $overrides = [] ): array {
		$this->repositories->projects()->insert(
			array_merge(
				[
					'project_id'              => 7001,
					'storage_uuid'            => 'bbbbbbbb-bbbb-4ccc-8ddd-eeeeeeeeeeee',
					'user_id'                 => 7,
					'order_id'                => 100,
					'order_item_id'           => 701,
					'product_id'              => 10,
					'template_id'             => '10',
					'status'                  => ProjectStatus::ACTIVE,
					'publication_status'      => PublicationStatus::PUBLISHED,
					'state_version'           => 1,
					'published_manifest_path' => 'published/manifest.json',
					'event_start_utc'         => '2026-07-01 12:00:00',
					'event_end_utc'           => '2026-07-01 18:00:00',
				],
				$overrides
			)
		);

		$project = $this->repositories->projects()->find_by_id( 7001 );
		$this->assertIsArray( $project );

		return $project;
	}
}

/**
 * Minimal WooCommerce order stub for refund tests.
 */
final class FakeWcOrder {

	/** @param array<int, int> $items */
	public function __construct(
		private int $id,
		private array $items,
		private bool $fully_refunded
	) {}

	public function get_items( string $type = 'line_item' ): array {
		unset( $type );
		$result = [];
		foreach ( $this->items as $item_id => $qty ) {
			$result[] = new FakeWcOrderItem( $item_id, $qty );
		}

		return $result;
	}

	public function get_refunds(): array {
		if ( ! $this->fully_refunded ) {
			return [];
		}

		$refund_items = [];
		foreach ( $this->items as $item_id => $qty ) {
			$refund_items[] = new FakeWcRefundItem( $item_id, $qty );
		}

		return [ new FakeWcRefund( $refund_items ) ];
	}
}

final class FakeWcOrderItem {

	public function __construct(
		private int $id,
		private int $qty
	) {}

	public function get_id(): int {
		return $this->id;
	}

	public function get_quantity(): int {
		return $this->qty;
	}
}

final class FakeWcRefund {

	/** @param list<FakeWcRefundItem> $items */
	public function __construct(
		private array $items
	) {}

	public function get_items( string $type = 'line_item' ): array {
		unset( $type );

		return $this->items;
	}
}

final class FakeWcRefundItem {

	public function __construct(
		private int $refunded_item_id,
		private int $qty
	) {}

	public function get_meta( string $key ) {
		if ( '_refunded_item_id' === $key ) {
			return $this->refunded_item_id;
		}

		return '';
	}

	public function get_quantity(): int {
		return -$this->qty;
	}
}
