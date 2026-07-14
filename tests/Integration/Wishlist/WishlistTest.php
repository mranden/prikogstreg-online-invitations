<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Wishlist;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Guest\RsvpStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;
use PrikOgStreg\OnlineInvitations\Domain\Wishlist\WishlistItemService;
use PrikOgStreg\OnlineInvitations\Domain\Wishlist\WishlistItemStatus;
use PrikOgStreg\OnlineInvitations\Domain\Wishlist\WishlistReservationService;
use PrikOgStreg\OnlineInvitations\Domain\Wishlist\WishlistSanitizer;
use PrikOgStreg\OnlineInvitations\Public\TokenResolution;
use PrikOgStreg\OnlineInvitations\Public\TokenResolver;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class WishlistTest extends TestCase {

	private FakeWpdb $wpdb;

	private RepositoryRegistry $repositories;

	private WishlistItemService $items;

	private WishlistReservationService $reservations;

	private TokenResolver $resolver;

	protected function setUp(): void {
		parent::setUp();

		$this->wpdb         = new FakeWpdb();
		$this->repositories = new RepositoryRegistry( $this->wpdb );
		$this->items        = new WishlistItemService(
			$this->repositories->wishlist_items(),
			$this->repositories->wishlist_reservations(),
			$this->repositories->projects(),
			$this->repositories->guests(),
			$this->repositories->events()
		);
		$this->reservations = new WishlistReservationService(
			$this->repositories->wishlist_items(),
			$this->repositories->wishlist_reservations(),
			$this->repositories->guests(),
			$this->repositories->events()
		);
		$this->resolver     = new TokenResolver( $this->repositories->guests(), $this->repositories->projects() );

		Functions\when( 'do_action' )->justReturn( null );
	}

	public function test_external_wishlist_url_validation(): void {
		$project = $this->seed_project();
		$this->assertNull( WishlistSanitizer::external_url( 'javascript:alert(1)' ) );
		$this->assertSame( 'https://onskeskyen.dk/list/abc', WishlistSanitizer::external_url( 'https://onskeskyen.dk/list/abc' ) );

		$result = $this->items->save_settings(
			$project,
			[ 'external_wishlist_url' => 'https://onskeskyen.dk/list/abc', 'internal_wishlist_enabled' => 1 ]
		);
		$this->assertTrue( $result['success'] );

		$updated = $this->repositories->projects()->find_by_id( (int) $project['project_id'] );
		$this->assertSame( 'https://onskeskyen.dk/list/abc', $updated['external_wishlist_url'] ?? '' );
	}

	public function test_item_crud_and_hidden_item_excluded_publicly(): void {
		$project = $this->seed_project( [ 'internal_wishlist_enabled' => 1 ] );
		$save    = $this->items->save_item(
			$project,
			[
				'title'              => 'Coffee maker',
				'description'        => 'Nice one',
				'external_url'       => 'https://shop.example/item',
				'quantity_requested' => 2,
				'status'             => WishlistItemStatus::ACTIVE,
			]
		);
		$this->assertTrue( $save['success'] );
		$item_id = (int) $save['item_id'];

		$hidden = $this->items->save_item(
			$project,
			[
				'wishlist_item_id'   => $item_id,
				'title'              => 'Secret gift',
				'quantity_requested' => 1,
				'status'             => WishlistItemStatus::HIDDEN,
			]
		);
		$this->assertTrue( $hidden['success'] );

		$token   = InvitationToken::generate();
		$guest_id = $this->seed_guest( (int) $project['project_id'], $token );
		$resolution = $this->resolver->resolve( $token['raw'] );
		$this->assertNotNull( $resolution );

		$public = $this->reservations->list_public_items( $resolution );
		$this->assertCount( 0, $public );
	}

	public function test_two_guest_race_for_final_item(): void {
		$project = $this->seed_project( [ 'internal_wishlist_enabled' => 1 ] );
		$item_id = $this->create_item( $project, 1 );

		$token_a = InvitationToken::generate();
		$token_b = InvitationToken::generate();
		$this->seed_guest( (int) $project['project_id'], $token_a, 'Guest A' );
		$this->seed_guest( (int) $project['project_id'], $token_b, 'Guest B' );

		$res_a = $this->resolver->resolve( $token_a['raw'] );
		$res_b = $this->resolver->resolve( $token_b['raw'] );
		$this->assertNotNull( $res_a );
		$this->assertNotNull( $res_b );

		$first = $this->reservations->reserve( $res_a, $item_id, [ 'quantity' => 1 ], 'race-a' );
		$second = $this->reservations->reserve( $res_b, $item_id, [ 'quantity' => 1 ], 'race-b' );

		$this->assertTrue( $first['success'] );
		$this->assertFalse( $second['success'] );
		$this->assertSame( 'insufficient_quantity', $second['error'] ?? '' );
	}

	public function test_multi_quantity_and_repeat_request_idempotent(): void {
		$project = $this->seed_project( [ 'internal_wishlist_enabled' => 1 ] );
		$item_id = $this->create_item( $project, 3 );
		$token   = InvitationToken::generate();
		$this->seed_guest( (int) $project['project_id'], $token );
		$resolution = $this->resolver->resolve( $token['raw'] );
		$this->assertNotNull( $resolution );

		$first = $this->reservations->reserve( $resolution, $item_id, [ 'quantity' => 2 ], 'idem-1' );
		$replay = $this->reservations->reserve( $resolution, $item_id, [ 'quantity' => 2 ], 'idem-1' );

		$this->assertTrue( $first['success'] );
		$this->assertTrue( $replay['success'] );
		$this->assertTrue( $replay['replayed'] ?? false );

		$row = $this->repositories->wishlist_items()->find_by_id( $item_id );
		$this->assertSame( 2, (int) ( $row['quantity_reserved'] ?? 0 ) );
	}

	public function test_release_own_reservation(): void {
		$project = $this->seed_project( [ 'internal_wishlist_enabled' => 1 ] );
		$item_id = $this->create_item( $project, 2 );
		$token   = InvitationToken::generate();
		$this->seed_guest( (int) $project['project_id'], $token );
		$resolution = $this->resolver->resolve( $token['raw'] );
		$this->assertNotNull( $resolution );

		$this->reservations->reserve( $resolution, $item_id, [ 'quantity' => 1 ], 'reserve-1' );
		$release = $this->reservations->release( $resolution, $item_id, [], 'release-1' );
		$this->assertTrue( $release['success'] );

		$row = $this->repositories->wishlist_items()->find_by_id( $item_id );
		$this->assertSame( 0, (int) ( $row['quantity_reserved'] ?? 0 ) );
	}

	public function test_surprise_privacy_hides_reserver_identity_by_default(): void {
		$project = $this->seed_project( [ 'internal_wishlist_enabled' => 1, 'show_reserver_identity' => 0 ] );
		$item_id = $this->create_item( $project, 1 );
		$token   = InvitationToken::generate();
		$this->seed_guest( (int) $project['project_id'], $token, 'Secret Guest' );
		$resolution = $this->resolver->resolve( $token['raw'] );
		$this->assertNotNull( $resolution );
		$this->reservations->reserve( $resolution, $item_id, [ 'quantity' => 1 ], 'privacy-1' );

		$owner_items = $this->items->list_for_owner( $project );
		$this->assertSame( [], $owner_items[0]['reservers'] ?? [] );
	}

	public function test_show_reserver_identity_when_enabled(): void {
		$project = $this->seed_project( [ 'internal_wishlist_enabled' => 1, 'show_reserver_identity' => 1 ] );
		$item_id = $this->create_item( $project, 1 );
		$token   = InvitationToken::generate();
		$this->seed_guest( (int) $project['project_id'], $token, 'Visible Guest' );
		$resolution = $this->resolver->resolve( $token['raw'] );
		$this->assertNotNull( $resolution );
		$this->reservations->reserve( $resolution, $item_id, [ 'quantity' => 1 ], 'visible-1' );

		$owner_items = $this->items->list_for_owner( $project );
		$this->assertSame( 'Visible Guest', $owner_items[0]['reservers'][0]['display_name'] ?? '' );
	}

	public function test_public_list_never_exposes_other_guest_identity(): void {
		$project = $this->seed_project( [ 'internal_wishlist_enabled' => 1 ] );
		$item_id = $this->create_item( $project, 2 );
		$token_a = InvitationToken::generate();
		$token_b = InvitationToken::generate();
		$this->seed_guest( (int) $project['project_id'], $token_a, 'Guest A' );
		$this->seed_guest( (int) $project['project_id'], $token_b, 'Guest B' );
		$res_a = $this->resolver->resolve( $token_a['raw'] );
		$res_b = $this->resolver->resolve( $token_b['raw'] );
		$this->assertNotNull( $res_a );
		$this->assertNotNull( $res_b );

		$this->reservations->reserve( $res_a, $item_id, [ 'quantity' => 1 ], 'a' );
		$public_b = $this->reservations->list_public_items( $res_b );
		$this->assertArrayNotHasKey( 'reservers', $public_b[0] );
		$this->assertSame( 0, (int) ( $public_b[0]['my_reserved_quantity'] ?? -1 ) );
	}

	public function test_invalid_token_resolution_fails(): void {
		$this->assertNull( $this->resolver->resolve( 'not-a-valid-token' ) );
	}

	public function test_restricted_project_rejects_reserve(): void {
		$project = $this->seed_project(
			[
				'internal_wishlist_enabled' => 1,
				'publication_status'        => PublicationStatus::UNPUBLISHED,
			]
		);
		$item_id = $this->create_item( $project, 1 );
		$token   = InvitationToken::generate();
		$guest_id = $this->seed_guest( (int) $project['project_id'], $token );
		$guest    = $this->repositories->guests()->find_by_id( $guest_id );
		$this->assertIsArray( $guest );

		$resolution = new TokenResolution(
			TokenResolution::TYPE_PERSONAL,
			$project,
			$guest
		);

		$result = $this->reservations->reserve( $resolution, $item_id, [ 'quantity' => 1 ], 'restricted' );
		$this->assertFalse( $result['success'] );
		$this->assertSame( 'unavailable', $result['error'] ?? '' );
	}

	public function test_xss_stripped_from_item_fields(): void {
		$project = $this->seed_project( [ 'internal_wishlist_enabled' => 1 ] );
		$result  = $this->items->save_item(
			$project,
			[
				'title'       => '<script>alert(1)</script>Gift',
				'description' => '<img onerror="evil()" src="x">',
			]
		);
		$this->assertTrue( $result['success'] );

		$row = $this->repositories->wishlist_items()->find_by_id( (int) $result['item_id'] );
		$this->assertStringNotContainsString( '<script', (string) ( $row['title'] ?? '' ) );
		$this->assertStringNotContainsString( 'onerror', (string) ( $row['description'] ?? '' ) );
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
					'storage_uuid'            => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
					'user_id'                 => 7,
					'order_id'                => 100,
					'order_item_id'           => 501,
					'product_id'              => 10,
					'template_id'             => '10',
					'status'                  => ProjectStatus::ACTIVE,
					'publication_status'      => PublicationStatus::PUBLISHED,
					'state_version'           => 1,
					'internal_wishlist_enabled' => 1,
					'show_reserver_identity'  => 0,
					'published_manifest_path' => '/tmp/manifest.json',
				],
				$overrides
			)
		);

		$project = $this->repositories->projects()->find_by_id( 5001 );
		$this->assertIsArray( $project );

		return $project;
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function create_item( array $project, int $quantity ): int {
		$result = $this->items->save_item(
			$project,
			[
				'title'              => 'Gift ' . $quantity,
				'quantity_requested' => $quantity,
				'status'             => WishlistItemStatus::ACTIVE,
			]
		);
		$this->assertTrue( $result['success'] );

		return (int) $result['item_id'];
	}

	/**
	 * @return int guest_id
	 */
	private function seed_guest( int $project_id, array $token, string $name = 'Guest' ): int {
		return $this->repositories->guests()->insert(
			[
				'project_id'   => $project_id,
				'display_name' => $name,
				'email'        => strtolower( str_replace( ' ', '', $name ) ) . '@example.com',
				'token_hash'   => $token['hash'],
				'rsvp_status'  => RsvpStatus::PENDING,
			]
		);
	}
}
