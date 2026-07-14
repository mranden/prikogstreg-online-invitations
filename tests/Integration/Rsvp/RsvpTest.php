<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Rsvp;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryType;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestCsv;
use PrikOgStreg\OnlineInvitations\Domain\Guest\RsvpStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;
use PrikOgStreg\OnlineInvitations\Domain\Rsvp\RsvpDeadlinePolicy;
use PrikOgStreg\OnlineInvitations\Domain\Rsvp\RsvpFormViewModel;
use PrikOgStreg\OnlineInvitations\Domain\Rsvp\RsvpSanitizer;
use PrikOgStreg\OnlineInvitations\Domain\Rsvp\RsvpService;
use PrikOgStreg\OnlineInvitations\Public\TokenResolver;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class RsvpTest extends TestCase {

	private FakeWpdb $wpdb;

	private RepositoryRegistry $repositories;

	private RsvpService $rsvp;

	private TokenResolver $resolver;

	protected function setUp(): void {
		parent::setUp();

		$this->wpdb         = new FakeWpdb();
		$this->repositories = new RepositoryRegistry( $this->wpdb );
		$this->resolver     = new TokenResolver( $this->repositories->guests(), $this->repositories->projects() );
		$this->rsvp         = new RsvpService(
			$this->repositories->guests(),
			$this->repositories->events(),
			new DeliveryQueueService( $this->repositories->deliveries() )
		);

		Functions\when( 'do_action' )->justReturn( null );
	}

	public function test_first_personal_response(): void {
		$token   = InvitationToken::generate();
		$project = $this->seed_project();
		$guest_id = $this->repositories->guests()->insert(
			[
				'project_id'   => (int) $project['project_id'],
				'display_name' => 'First Guest',
				'email'        => 'guest@example.com',
				'token_hash'   => $token['hash'],
				'rsvp_status'  => RsvpStatus::PENDING,
			]
		);

		$resolution = $this->resolver->resolve( $token['raw'] );
		$this->assertNotNull( $resolution );

		$result = $this->rsvp->submit_personal(
			$resolution,
			[ 'attending' => 'yes', 'attendee_count' => 2 ],
			'idem-first'
		);

		$this->assertTrue( $result['success'] );
		$guest = $this->repositories->guests()->find_by_id( $guest_id );
		$this->assertSame( RsvpStatus::ATTENDING, $guest['rsvp_status'] ?? '' );
		$this->assertSame( 2, (int) ( $guest['attendee_count'] ?? 0 ) );
		$this->assertNotSame( '', (string) ( $guest['responded_at_utc'] ?? '' ) );
	}

	public function test_response_change_logs_event_and_queues_once(): void {
		$token   = InvitationToken::generate();
		$project = $this->seed_project( [ 'public_contact_email' => 'owner@example.com' ] );
		$guest_id = $this->repositories->guests()->insert(
			[
				'project_id'   => (int) $project['project_id'],
				'display_name' => 'Changing Guest',
				'email'        => 'guest@example.com',
				'token_hash'   => $token['hash'],
				'rsvp_status'  => RsvpStatus::PENDING,
			]
		);

		$resolution = $this->resolver->resolve( $token['raw'] );
		$this->assertNotNull( $resolution );

		$this->rsvp->submit_personal( $resolution, [ 'attending' => 'yes', 'attendee_count' => 1 ], 'idem-a' );
		$this->rsvp->submit_personal( $resolution, [ 'attending' => 'no' ], 'idem-b' );

		$guest = $this->repositories->guests()->find_by_id( $guest_id );
		$this->assertSame( RsvpStatus::DECLINED, $guest['rsvp_status'] ?? '' );

		$events = $this->repositories->events()->list_rsvp_events_for_project( (int) $project['project_id'], 10 );
		$this->assertGreaterThanOrEqual( 2, count( $events ) );

		$delivery_count = $this->wpdb->table_count( 'wp_pks_oi_deliveries' );
		$this->assertSame( 4, $delivery_count );

		$replay = $this->rsvp->submit_personal( $resolution, [ 'attending' => 'no' ], 'idem-b' );
		$this->assertTrue( $replay['success'] );
		$this->assertTrue( $replay['replayed'] ?? false );
		$this->assertSame( 4, $this->wpdb->table_count( 'wp_pks_oi_deliveries' ) );
	}

	public function test_rejects_after_deadline(): void {
		$token   = InvitationToken::generate();
		$project = $this->seed_project(
			[
				'rsvp_deadline_utc' => gmdate( 'Y-m-d H:i:s', time() - 3600 ),
			]
		);
		$this->repositories->guests()->insert(
			[
				'project_id'   => (int) $project['project_id'],
				'display_name' => 'Late Guest',
				'token_hash'   => $token['hash'],
				'rsvp_status'  => RsvpStatus::PENDING,
			]
		);

		$resolution = $this->resolver->resolve( $token['raw'] );
		$result     = $this->rsvp->submit_personal( $resolution, [ 'attending' => 'yes' ], 'idem-late' );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'deadline_closed', $result['error'] ?? '' );
	}

	public function test_rejects_restricted_project(): void {
		$token   = InvitationToken::generate();
		$project = $this->seed_project( [ 'restricted_at_utc' => gmdate( 'Y-m-d H:i:s' ) ] );
		$this->repositories->guests()->insert(
			[
				'project_id'   => (int) $project['project_id'],
				'display_name' => 'Restricted Guest',
				'token_hash'   => $token['hash'],
			]
		);

		$resolution = $this->resolver->resolve( $token['raw'] );
		$result     = $this->rsvp->submit_personal( $resolution, [ 'attending' => 'yes' ], 'idem-restricted' );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'unavailable', $result['error'] ?? '' );
	}

	public function test_invalid_token_context_fails(): void {
		$generic = InvitationToken::generate();
		$this->seed_project( [ 'generic_token_hash' => $generic['hash'] ] );
		$resolution = $this->resolver->resolve( $generic['raw'] );
		$this->assertNotNull( $resolution );

		$result = $this->rsvp->submit_personal( $resolution, [ 'attending' => 'yes' ], 'idem-invalid' );
		$this->assertFalse( $result['success'] );
		$this->assertSame( 'invalid_context', $result['error'] ?? '' );
	}

	public function test_generic_response_creates_guest_with_flag(): void {
		$generic = InvitationToken::generate();
		$project = $this->seed_project( [ 'generic_token_hash' => $generic['hash'] ] );
		$resolution = $this->resolver->resolve( $generic['raw'] );

		$result = $this->rsvp->submit_generic(
			$resolution,
			[
				'display_name'   => 'Walk-in Guest',
				'email'          => 'walkin@example.com',
				'attending'      => 'yes',
				'attendee_count' => 1,
			],
			'idem-generic-1',
			'client-a'
		);

		$this->assertTrue( $result['success'] );
		$this->assertNotEmpty( $result['invitation_url'] ?? '' );
		$guest = $this->repositories->guests()->find_by_id( (int) ( $result['guest']['guest_id'] ?? 0 ) );
		$this->assertSame( 1, (int) ( $guest['is_generic_response'] ?? 0 ) );
		$this->assertSame( 'Walk-in Guest', $guest['display_name'] ?? '' );
	}

	public function test_generic_does_not_overwrite_named_guest(): void {
		$generic = InvitationToken::generate();
		$project = $this->seed_project( [ 'generic_token_hash' => $generic['hash'] ] );
		$named_id = $this->repositories->guests()->insert(
			[
				'project_id'   => (int) $project['project_id'],
				'display_name' => 'Named Guest',
				'email'        => 'same@example.com',
				'token_hash'   => InvitationToken::generate()['hash'],
				'rsvp_status'  => RsvpStatus::ATTENDING,
			]
		);

		$resolution = $this->resolver->resolve( $generic['raw'] );
		$result     = $this->rsvp->submit_generic(
			$resolution,
			[
				'display_name' => 'Named Guest',
				'email'        => 'same@example.com',
				'attending'    => 'no',
			],
			'idem-generic-2',
			'client-b'
		);

		$this->assertTrue( $result['success'] );
		$this->assertNotSame( $named_id, (int) ( $result['guest']['guest_id'] ?? 0 ) );
		$named = $this->repositories->guests()->find_by_id( $named_id );
		$this->assertSame( RsvpStatus::ATTENDING, $named['rsvp_status'] ?? '' );
	}

	public function test_generic_abuse_rate_limit(): void {
		$generic = InvitationToken::generate();
		$project = $this->seed_project( [ 'generic_token_hash' => $generic['hash'] ] );
		$resolution = $this->resolver->resolve( $generic['raw'] );

		for ( $i = 0; $i < RsvpService::GENERIC_RATE_MAX; ++$i ) {
			$result = $this->rsvp->submit_generic(
				$resolution,
				[
					'display_name' => 'Guest ' . $i,
					'attending'    => 'yes',
					'attendee_count' => 1,
				],
				'idem-rate-' . $i,
				'same-client'
			);
			$this->assertTrue( $result['success'] );
		}

		$blocked = $this->rsvp->submit_generic(
			$resolution,
			[
				'display_name' => 'Blocked Guest',
				'attending'    => 'yes',
				'attendee_count' => 1,
			],
			'idem-rate-blocked',
			'same-client'
		);

		$this->assertFalse( $blocked['success'] );
		$this->assertSame( 'rate_limited', $blocked['error'] ?? '' );
	}

	public function test_attendee_count_validation(): void {
		$token   = InvitationToken::generate();
		$project = $this->seed_project( [ 'attendee_count_enabled' => 1 ] );
		$this->repositories->guests()->insert(
			[
				'project_id'   => (int) $project['project_id'],
				'display_name' => 'Count Guest',
				'token_hash'   => $token['hash'],
			]
		);

		$resolution = $this->resolver->resolve( $token['raw'] );
		$missing    = $this->rsvp->submit_personal( $resolution, [ 'attending' => 'yes' ], 'idem-count-missing' );
		$this->assertFalse( $missing['success'] );
		$this->assertSame( 'missing_attendee_count', $missing['error'] ?? '' );

		$invalid = $this->rsvp->submit_personal(
			$resolution,
			[ 'attending' => 'yes', 'attendee_count' => 99 ],
			'idem-count-invalid'
		);
		$this->assertFalse( $invalid['success'] );
		$this->assertSame( 'invalid_attendee_count', $invalid['error'] ?? '' );
	}

	public function test_form_exposes_invited_attendee_count_for_pending_guest(): void {
		$token   = InvitationToken::generate();
		$project = $this->seed_project( [ 'attendee_count_enabled' => 1 ] );
		$this->repositories->guests()->insert(
			[
				'project_id'     => (int) $project['project_id'],
				'display_name'   => 'Family Hansen',
				'token_hash'     => $token['hash'],
				'attendee_count' => 2,
				'rsvp_status'    => RsvpStatus::PENDING,
			]
		);

		$resolution = $this->resolver->resolve( $token['raw'] );
		$this->assertNotNull( $resolution );

		$form = RsvpFormViewModel::from_resolution( $resolution );
		$this->assertSame( 2, $form->config['invited_attendee_count'] );
		$this->assertSame( 2, $form->config['attendee_count'] );
	}

	public function test_xss_stripped_from_comments(): void {
		$dirty = '<script>alert(1)</script>Hello';
		$clean = RsvpSanitizer::comment( $dirty );
		$this->assertStringNotContainsString( '<script>', $clean );
		$this->assertStringContainsString( 'Hello', $clean );
	}

	public function test_csv_export_maps_responded_at(): void {
		$project = $this->seed_project();
		$this->repositories->guests()->insert(
			[
				'project_id'       => (int) $project['project_id'],
				'display_name'     => 'Exporter',
				'token_hash'       => InvitationToken::generate()['hash'],
				'rsvp_status'      => RsvpStatus::ATTENDING,
				'responded_at_utc' => '2026-07-01 12:00:00',
			]
		);

		$rows = $this->repositories->guests()->export_rows_for_project( (int) $project['project_id'] );
		$csv  = GuestCsv::build_export( $rows );
		$this->assertStringContainsString( 'responded_at', $csv );
		$this->assertStringContainsString( '2026-07-01 12:00:00', $csv );
	}

	public function test_response_event_history_recorded(): void {
		$token   = InvitationToken::generate();
		$project = $this->seed_project();
		$this->repositories->guests()->insert(
			[
				'project_id'   => (int) $project['project_id'],
				'display_name' => 'History Guest',
				'token_hash'   => $token['hash'],
			]
		);

		$resolution = $this->resolver->resolve( $token['raw'] );
		$this->rsvp->submit_personal( $resolution, [ 'attending' => 'yes', 'attendee_count' => 1 ], 'idem-history' );

		$events = $this->repositories->events()->list_rsvp_events_for_project( (int) $project['project_id'], 5 );
		$this->assertNotEmpty( $events );
		$this->assertSame( 'guest_rsvp_submitted', $events[0]['event_type'] ?? '' );
	}

	public function test_deadline_policy_open_when_empty(): void {
		$this->assertTrue( RsvpDeadlinePolicy::is_open( [] ) );
	}

	public function test_organizer_and_confirmation_deliveries_queued(): void {
		$token   = InvitationToken::generate();
		$project = $this->seed_project( [ 'public_contact_email' => 'owner@example.com' ] );
		$this->repositories->guests()->insert(
			[
				'project_id'   => (int) $project['project_id'],
				'display_name' => 'Notify Guest',
				'email'        => 'guest@example.com',
				'token_hash'   => $token['hash'],
			]
		);

		$resolution = $this->resolver->resolve( $token['raw'] );
		$this->rsvp->submit_personal( $resolution, [ 'attending' => 'yes', 'attendee_count' => 1 ], 'idem-notify' );

		$rows = $this->wpdb->get_results( 'SELECT * FROM wp_pks_oi_deliveries', ARRAY_A );
		$types = array_column( $rows, 'delivery_type' );
		$this->assertContains( DeliveryType::RSVP_CONFIRMATION, $types );
		$this->assertContains( DeliveryType::ORGANIZER_RSVP, $types );
	}

	/**
	 * @param array<string, mixed> $overrides
	 * @return array<string, mixed>
	 */
	private function seed_project( array $overrides = [] ): array {
		$this->repositories->projects()->insert(
			array_merge(
				[
					'project_id'              => 5001,
					'storage_uuid'            => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
					'user_id'                 => 7,
					'order_id'                => 200,
					'order_item_id'           => 6001,
					'product_id'              => 10,
					'template_id'             => '10',
					'status'                  => ProjectStatus::ACTIVE,
					'publication_status'      => PublicationStatus::PUBLISHED,
					'event_title'             => 'RSVP party',
					'attendee_count_enabled'  => 1,
					'comment_enabled'         => 1,
					'dietary_notes_enabled'   => 1,
					'published_manifest_path' => 'published/manifest.json',
				],
				$overrides
			)
		);

		$project = $this->repositories->projects()->find_by_id( 5001 );
		$this->assertIsArray( $project );

		return $project;
	}
}
