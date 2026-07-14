<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Delivery;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryRecipientResolver;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliverySendService;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryStatus;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryType;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\InvitationSendService;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestSendTokenStore;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestTokenService;
use PrikOgStreg\OnlineInvitations\Domain\Guest\RsvpStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;
use PrikOgStreg\OnlineInvitations\Scheduling\ReminderScheduler;
use PrikOgStreg\OnlineInvitations\Scheduling\SchedulerRegistrar;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;
use PrikOgStreg\OnlineInvitations\WooCommerce\Emails\EmailRegistry;
use PrikOgStreg\OnlineInvitations\Tests\Support\DeliveryEnvironment;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class DeliveryTest extends TestCase {

	private FakeWpdb $wpdb;

	private RepositoryRegistry $repositories;

	private DeliveryQueueService $queue;

	private DeliverySendService $sender;

	private ReminderScheduler $reminders;

	protected function setUp(): void {
		parent::setUp();

		$this->wpdb         = new FakeWpdb();
		$this->repositories = new RepositoryRegistry( $this->wpdb );

		DeliveryEnvironment::bootstrap();

		$this->queue        = new DeliveryQueueService( $this->repositories->deliveries() );
		$this->sender       = new DeliverySendService(
			$this->repositories->deliveries(),
			new DeliveryRecipientResolver( $this->repositories->projects(), $this->repositories->guests() ),
			$this->repositories->guests(),
			new GuestTokenService( $this->repositories->guests() )
		);

		( new SchedulerRegistrar( $this->repositories ) )->register();
		$this->reminders = new ReminderScheduler(
			$this->repositories->projects(),
			$this->repositories->guests(),
			$this->queue
		);

		Functions\when( 'do_action' )->justReturn( null );
		$GLOBALS['pks_oi_test_mail_log'] = [];
		$GLOBALS['pks_oi_test_mail_fail']  = false;
		$GLOBALS['pks_oi_test_as_defer_sync'] = false;
	}

	public function test_email_classes_register_with_woocommerce(): void {
		$registry = new EmailRegistry();
		$classes  = $registry->add_email_classes( [] );

		$this->assertArrayHasKey( 'pks_oi_project_welcome', $classes );
		$this->assertArrayHasKey( 'pks_oi_guest_invitation', $classes );
		$this->assertArrayHasKey( 'pks_oi_rsvp_reminder', $classes );
	}

	public function test_queue_and_send_welcome_delivery(): void {
		$project = $this->seed_project();
		$delivery_id = $this->queue->queue_welcome( $project );

		$this->assertGreaterThan( 0, $delivery_id );
		$row = $this->repositories->deliveries()->find_by_id( $delivery_id );
		$this->assertSame( DeliveryStatus::SENT, $row['status'] ?? '' );
		$this->assertNotEmpty( $GLOBALS['pks_oi_test_mail_log'] );
		$this->assertSame( 'user7@example.com', $GLOBALS['pks_oi_test_mail_log'][0]['to'] ?? '' );
	}

	public function test_duplicate_queue_returns_same_delivery_row(): void {
		$project = $this->seed_project();
		$first   = $this->queue->queue_welcome( $project );
		$second  = $this->queue->queue_welcome( $project );

		$this->assertGreaterThan( 0, $first );
		$this->assertSame( 0, $second );
		$this->assertSame( 1, $this->wpdb->table_count( 'wp_pks_oi_deliveries' ) );
	}

	public function test_transient_retry_then_succeeds(): void {
		$project     = $this->seed_project();
		$delivery_id = $this->queue->queue_welcome( $project );

		$this->repositories->deliveries()->update(
			$delivery_id,
			[
				'status'        => DeliveryStatus::QUEUED,
				'attempt_count' => 0,
				'sent_at_utc'   => null,
			]
		);

		$GLOBALS['pks_oi_test_as_defer_sync'] = true;
		$GLOBALS['pks_oi_test_mail_fail'] = true;
		$this->assertFalse( $this->sender->process_delivery( $delivery_id ) );

		$retry = $this->repositories->deliveries()->find_by_id( $delivery_id );
		$this->assertSame( DeliveryStatus::QUEUED, $retry['status'] ?? '' );
		$this->assertSame( 1, (int) ( $retry['attempt_count'] ?? 0 ) );

		$GLOBALS['pks_oi_test_mail_fail'] = false;
		$this->assertTrue( $this->sender->process_delivery( $delivery_id ) );
		$this->assertSame( DeliveryStatus::SENT, $this->repositories->deliveries()->find_by_id( $delivery_id )['status'] ?? '' );
	}

	public function test_permanent_failure_after_max_attempts(): void {
		$project     = $this->seed_project();
		$delivery_id = $this->queue->queue_welcome( $project );

		$this->repositories->deliveries()->update(
			$delivery_id,
			[
				'status'        => DeliveryStatus::QUEUED,
				'attempt_count' => 2,
				'sent_at_utc'   => null,
			]
		);

		$GLOBALS['pks_oi_test_mail_fail'] = true;
		$this->assertFalse( $this->sender->process_delivery( $delivery_id ) );

		$row = $this->repositories->deliveries()->find_by_id( $delivery_id );
		$this->assertSame( DeliveryStatus::FAILED, $row['status'] ?? '' );
		$this->assertSame( 'send_failed', $row['last_error_code'] ?? '' );
	}

	public function test_reminder_scheduled_five_days_before_deadline(): void {
		$deadline = gmdate( 'Y-m-d H:i:s', time() + ( 10 * DAY_IN_SECONDS ) );
		$project  = $this->seed_published_project(
			[
				'rsvp_deadline_utc'   => $deadline,
				'reminder_offset_days' => 5,
				'published_manifest_path' => '/tmp/manifest.json',
			]
		);
		$guest_id = $this->seed_guest( (int) $project['project_id'], 'guest@example.com' );
		$GLOBALS['pks_oi_test_as_defer_sync'] = true;
		$this->reminders->reschedule( (int) $project['project_id'] );

		$row = $this->repositories->deliveries()->find_by_idempotency_key(
			'reminder:' . (int) $project['project_id'] . ':' . $guest_id . ':' . substr( $deadline, 0, 10 )
		);
		$this->assertIsArray( $row );
		$expected = strtotime( $deadline . ' UTC' ) - ( 5 * DAY_IN_SECONDS );
		$this->assertSame( gmdate( 'Y-m-d H:i:s', $expected ), $row['scheduled_at_utc'] ?? '' );
	}

	public function test_deadline_change_reschedules_reminder(): void {
		$deadline = gmdate( 'Y-m-d H:i:s', time() + ( 12 * DAY_IN_SECONDS ) );
		$project  = $this->seed_published_project(
			[
				'rsvp_deadline_utc'       => $deadline,
				'published_manifest_path' => '/tmp/manifest.json',
			]
		);
		$guest_id = $this->seed_guest( (int) $project['project_id'], 'guest@example.com' );
		$GLOBALS['pks_oi_test_as_defer_sync'] = true;
		$this->reminders->reschedule( (int) $project['project_id'] );

		$new_deadline = gmdate( 'Y-m-d H:i:s', time() + ( 20 * DAY_IN_SECONDS ) );
		$this->repositories->projects()->update(
			(int) $project['project_id'],
			[ 'rsvp_deadline_utc' => $new_deadline ]
		);
		$this->reminders->reschedule( (int) $project['project_id'] );

		$old_key = 'reminder:' . (int) $project['project_id'] . ':' . $guest_id . ':' . substr( $deadline, 0, 10 );
		$new_key = 'reminder:' . (int) $project['project_id'] . ':' . $guest_id . ':' . substr( $new_deadline, 0, 10 );
		$old_row = $this->repositories->deliveries()->find_by_idempotency_key( $old_key );
		$new_row = $this->repositories->deliveries()->find_by_idempotency_key( $new_key );

		$this->assertIsArray( $old_row );
		$this->assertSame( DeliveryStatus::CANCELLED, $old_row['status'] ?? '' );
		$this->assertIsArray( $new_row );
		$this->assertSame( DeliveryStatus::QUEUED, $new_row['status'] ?? '' );
	}

	public function test_skip_responded_guest_for_reminder(): void {
		$deadline = gmdate( 'Y-m-d H:i:s', time() + ( 10 * DAY_IN_SECONDS ) );
		$project  = $this->seed_published_project(
			[
				'rsvp_deadline_utc'       => $deadline,
				'published_manifest_path' => '/tmp/manifest.json',
			]
		);
		$guest_id = $this->seed_guest( (int) $project['project_id'], 'guest@example.com', RsvpStatus::ATTENDING );
		$this->reminders->reschedule( (int) $project['project_id'] );

		$key = 'reminder:' . (int) $project['project_id'] . ':' . $guest_id . ':' . substr( $deadline, 0, 10 );
		$this->assertNull( $this->repositories->deliveries()->find_by_idempotency_key( $key ) );
	}

	public function test_unpublish_cancels_queued_reminders(): void {
		$deadline = gmdate( 'Y-m-d H:i:s', time() + ( 10 * DAY_IN_SECONDS ) );
		$project  = $this->seed_published_project(
			[
				'rsvp_deadline_utc'       => $deadline,
				'published_manifest_path' => '/tmp/manifest.json',
			]
		);
		$this->seed_guest( (int) $project['project_id'], 'guest@example.com' );
		$GLOBALS['pks_oi_test_as_defer_sync'] = true;
		$this->reminders->reschedule( (int) $project['project_id'] );
		$this->assertGreaterThan( 0, $this->wpdb->table_count( 'wp_pks_oi_deliveries' ) );

		$this->reminders->cancel_for_project( (int) $project['project_id'] );

		$rows = $this->repositories->deliveries()->list_by_project_and_status(
			(int) $project['project_id'],
			DeliveryStatus::CANCELLED,
			DeliveryType::RSVP_REMINDER
		);
		$this->assertNotEmpty( $rows );
	}

	public function test_guest_invitation_send_does_not_log_raw_token(): void {
		$token   = InvitationToken::generate();
		$project = $this->seed_published_project( [ 'published_manifest_path' => '/tmp/manifest.json' ] );
		$guest_id = $this->repositories->guests()->insert(
			[
				'project_id'   => (int) $project['project_id'],
				'display_name' => 'Token Guest',
				'email'        => 'token-guest@example.com',
				'token_hash'   => $token['hash'],
				'rsvp_status'  => RsvpStatus::PENDING,
			]
		);
		GuestSendTokenStore::remember( $guest_id, $token['raw'] );

		$send = new InvitationSendService( $this->repositories->guests(), $this->queue );
		$result = $send->send_to_guests( $project, [ $guest_id ], false );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 1, $result['queued'] );

		$message = (string) ( $GLOBALS['pks_oi_test_mail_log'][0]['message'] ?? '' );
		$this->assertStringNotContainsString( $token['hash'], $message );
		$this->assertStringContainsString( '/invitation/', $message );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function seed_project( array $overrides = [] ): array {
		$this->repositories->projects()->insert(
			array_merge(
				[
					'project_id'    => 4001,
					'storage_uuid'  => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
					'user_id'       => 7,
					'order_id'      => 100,
					'order_item_id' => 501,
					'product_id'    => 10,
					'template_id'   => '10',
					'status'        => ProjectStatus::ACTIVE,
					'state_version' => 1,
				],
				$overrides
			)
		);

		$project = $this->repositories->projects()->find_by_id( 4001 );
		$this->assertIsArray( $project );

		return $project;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function seed_published_project( array $overrides = [] ): array {
		return $this->seed_project(
			array_merge(
				[
					'publication_status' => PublicationStatus::PUBLISHED,
					'event_title'        => 'Party',
				],
				$overrides
			)
		);
	}

	private function seed_guest( int $project_id, string $email, string $rsvp = RsvpStatus::PENDING ): int {
		$token = InvitationToken::generate();
		$guest_id = $this->repositories->guests()->insert(
			[
				'project_id'   => $project_id,
				'display_name' => 'Guest',
				'email'        => $email,
				'token_hash'   => $token['hash'],
				'rsvp_status'  => $rsvp,
			]
		);
		GuestSendTokenStore::remember( $guest_id, $token['raw'] );

		return $guest_id;
	}
}
