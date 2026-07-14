<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\E2E;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Domain\Guest\RsvpStatus;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoServiceFactory;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectEventService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectFactory;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectPublishService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectRestrictionService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectLifecycleAudit;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStateService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicEntitlement;
use PrikOgStreg\OnlineInvitations\Domain\Rsvp\RsvpService;
use PrikOgStreg\OnlineInvitations\Domain\Wishlist\WishlistItemService;
use PrikOgStreg\OnlineInvitations\Domain\Wishlist\WishlistReservationService;
use PrikOgStreg\OnlineInvitations\Public\PosterDisplayAssets;
use PrikOgStreg\OnlineInvitations\Public\PublicInvitationLoader;
use PrikOgStreg\OnlineInvitations\Public\TokenResolver;
use PrikOgStreg\OnlineInvitations\Scheduling\WelcomeScheduler;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;
use PrikOgStreg\OnlineInvitations\Storage\StorageRegistry;
use PrikOgStreg\OnlineInvitations\Tests\Support\DeliveryEnvironment;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeBuilderAdapter;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWcOrder;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWcOrderItem;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWcProduct;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\Support\PhotoFixtures;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;
use PrikOgStreg\OnlineInvitations\WooCommerce\Orders\OrderRefundDetector;
use PrikOgStreg\OnlineInvitations\WooCommerce\Orders\ProjectRefundListener;

/**
 * Best-effort PHP chain covering the V1 invitation journey without a browser.
 */
final class InvitationFlowChainTest extends TestCase {

	private string $storage_root;

	private FakeWpdb $wpdb;

	private RepositoryRegistry $repositories;

	private ProjectService $projects;

	private FakeBuilderAdapter $adapter;

	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 ) . '/stubs/bpp/Builder_Adapter_Interface.php';

		$this->storage_root = sys_get_temp_dir() . '/pks-oi-e2e-' . uniqid( '', true );
		$this->wpdb         = new FakeWpdb();
		$this->repositories = new RepositoryRegistry( $this->wpdb );
		$this->adapter      = new FakeBuilderAdapter();

		Functions\when( 'has_filter' )->justReturn( true );
		Functions\when( 'apply_filters' )->justReturn( $this->adapter );
		Functions\when( 'wp_insert_post' )->justReturn( 3001 );
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'get_current_user_id' )->justReturn( 0 );

		$builder = new BuilderService();
		$builder->resolve();
		DeliveryEnvironment::bootstrap();

		$this->projects = new ProjectService(
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

	public function test_checkout_to_refund_restriction_chain(): void {
		$product = new FakeWcProduct( 10 );
		$simple  = new FakeWcProduct( 20, 'simple', 'Simple Product' );
		$order   = new FakeWcOrder(
			600,
			7,
			'processing',
			[
				'line_item' => [
					601 => new FakeWcOrderItem( 601, 10, $product, true ),
					602 => new FakeWcOrderItem( 602, 20, $simple, false ),
				],
			]
		);

		$this->projects->process_order( $order );
		$project = $this->repositories->projects()->find_by_order_item_id( 601 );
		$this->assertIsArray( $project );
		$project_id = (int) $project['project_id'];

		$events = new ProjectEventService( $this->repositories->projects(), $this->repositories->events() );
		$save   = $events->save_event_details(
			$project,
			[
				'event_title'     => 'Chain Party',
				'event_start_utc' => '2026-09-01 18:00:00',
				'event_end_utc'   => '2026-09-01 22:00:00',
			]
		);
		$this->assertTrue( $save['success'] );

		$project = $this->repositories->projects()->find_by_id( $project_id );
		$this->assertIsArray( $project );

		$storage       = ( new StorageRegistry( $this->storage_root ) )->project_storage();
		$builder       = new BuilderService();
		$builder->resolve();
		$state_service = new ProjectStateService(
			$builder,
			$storage,
			$this->repositories->projects(),
			$this->repositories->events()
		);

		$publish_service = new ProjectPublishService(
			$builder,
			$storage,
			$this->repositories->projects(),
			$state_service,
			$this->repositories->events()
		);

		$generic = InvitationToken::generate();
		$this->repositories->projects()->update(
			$project_id,
			[ 'generic_token_hash' => $generic['hash'], 'internal_wishlist_enabled' => 1, 'guest_photos_enabled' => 1 ]
		);
		$project = $this->repositories->projects()->find_by_id( $project_id );
		$this->assertIsArray( $project );

		$published = $publish_service->publish( $project );
		$this->assertTrue( $published['success'] ?? false );

		$guest_token = InvitationToken::generate();
		$this->repositories->guests()->insert(
			[
				'project_id'   => $project_id,
				'display_name' => 'Chain Guest',
				'email'        => 'chain@example.com',
				'token_hash'   => $guest_token['hash'],
				'rsvp_status'  => RsvpStatus::PENDING,
			]
		);

		$resolver   = new TokenResolver( $this->repositories->guests(), $this->repositories->projects() );
		$resolution = $resolver->resolve( $guest_token['raw'] );
		$this->assertNotNull( $resolution );

		$loader = new PublicInvitationLoader( $storage, $builder, new PosterDisplayAssets( $storage ) );
		$loaded = $loader->load_published_content( $resolution->project() );
		$this->assertTrue( $loaded['success'] ?? false );

		$rsvp = new RsvpService(
			$this->repositories->guests(),
			$this->repositories->events(),
			new DeliveryQueueService( $this->repositories->deliveries() )
		);
		$rsvp_result = $rsvp->submit_personal( $resolution, [ 'attending' => 'yes', 'attendee_count' => 2 ], 'chain-rsvp' );
		$this->assertTrue( $rsvp_result['success'] );

		$wishlist_items = new WishlistItemService(
			$this->repositories->wishlist_items(),
			$this->repositories->wishlist_reservations(),
			$this->repositories->projects(),
			$this->repositories->guests(),
			$this->repositories->events()
		);
		$item_save = $wishlist_items->save_item(
			$resolution->project(),
			[
				'title'              => 'Flow gift',
				'quantity_requested' => 1,
			]
		);
		$this->assertTrue( $item_save['success'] );
		$item_id = (int) ( $item_save['item_id'] ?? 0 );

		$reservations = new WishlistReservationService(
			$this->repositories->wishlist_items(),
			$this->repositories->wishlist_reservations(),
			$this->repositories->guests(),
			$this->repositories->events()
		);
		$reserve = $reservations->reserve( $resolution, $item_id, [ 'quantity' => 1 ], 'chain-reserve' );
		$this->assertTrue( $reserve['success'] );

		$photos = PhotoServiceFactory::create( $this->repositories, new StorageRegistry( $this->storage_root ) );
		$intent = $photos->issue_intent( $resolution, $guest_token['raw'] );
		$this->assertTrue( $intent['success'] );
		$upload = $photos->upload(
			$resolution,
			$guest_token['raw'],
			(string) $intent['intent'],
			[ PhotoFixtures::file_from_bytes( PhotoFixtures::png_1x1(), 'chain.png' ) ]
		);
		$this->assertTrue( $upload['success'] );

		$refund_order = new RefundAwareFakeWcOrder( 600, [ 601 => 1 ], true );
		Functions\when( 'wc_get_order' )->justReturn( $refund_order );

		$listener = new ProjectRefundListener(
			$this->repositories->projects(),
			new OrderRefundDetector(),
			new ProjectRestrictionService(
				$this->repositories->projects(),
				new DeliveryQueueService( $this->repositories->deliveries() ),
				new ProjectLifecycleAudit( $this->repositories->events() )
			)
		);
		$listener->handle_order_refunded( 600, 601 );

		$restricted = $this->repositories->projects()->find_by_id( $project_id );
		$this->assertIsArray( $restricted );
		$this->assertSame( ProjectStatus::RESTRICTED, $restricted['status'] ?? '' );
		$this->assertFalse( PublicEntitlement::is_publicly_available( $restricted ) );
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

/**
 * Refund-aware order stub for E2E refund restriction step.
 */
final class RefundAwareFakeWcOrder {

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
			$result[] = new RefundAwareFakeWcOrderItem( $item_id, $qty );
		}

		return $result;
	}

	public function get_refunds(): array {
		if ( ! $this->fully_refunded ) {
			return [];
		}

		$refund_items = [];
		foreach ( $this->items as $item_id => $qty ) {
			$refund_items[] = new RefundAwareFakeWcRefundItem( $item_id, $qty );
		}

		return [ new RefundAwareFakeWcRefund( $refund_items ) ];
	}
}

final class RefundAwareFakeWcOrderItem {

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

final class RefundAwareFakeWcRefund {

	/** @param list<RefundAwareFakeWcRefundItem> $items */
	public function __construct(
		private array $items
	) {}

	public function get_items( string $type = 'line_item' ): array {
		unset( $type );

		return $this->items;
	}
}

final class RefundAwareFakeWcRefundItem {

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
