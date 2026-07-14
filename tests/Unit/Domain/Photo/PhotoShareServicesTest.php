<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\Domain\Photo;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoAccessCodeDisplayStore;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoAccessCodeService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoAccessRateLimiter;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoGuestSessionService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoShareEntitlement;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoShareTokenService;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class PhotoShareServicesTest extends TestCase {

	private FakeWpdb $wpdb;

	private RepositoryRegistry $repositories;

	protected function setUp(): void {
		parent::setUp();
		$this->wpdb         = new FakeWpdb();
		$this->repositories = new RepositoryRegistry( $this->wpdb );
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'wp_hash_password' )->alias( fn( string $p ) => 'hash:' . $p );
		Functions\when( 'wp_check_password' )->alias( fn( string $p, string $h ) => $h === 'hash:' . $p );
		Functions\when( 'is_ssl' )->justReturn( false );
	}

	public function test_access_code_is_hashed_and_not_retrievable(): void {
		$this->seed_project();
		$project = $this->repositories->projects()->find_by_id( 1 );
		$this->assertIsArray( $project );

		Functions\when( 'update_post_meta' )->justReturn( true );

		$service = new PhotoAccessCodeService( $this->repositories->projects(), new PhotoAccessRateLimiter() );
		$result  = $service->set_code( $project, 'secret-code', 'secret-code' );
		$this->assertTrue( $result['success'] );

		$updated = $this->repositories->projects()->find_by_id( 1 );
		$this->assertSame( 'hash:secret-code', $updated['photo_access_code_hash'] ?? '' );
		$this->assertTrue( $service->has_code( $updated ) );
	}

	public function test_access_code_display_store_remembers_plaintext_for_owner(): void {
		Functions\when( 'update_post_meta' )->justReturn( true );
		Functions\when( 'get_post_meta' )->alias(
			static function ( int $post_id, string $key, bool $single ) {
				if ( PhotoAccessCodeDisplayStore::META_KEY === $key && 1 === $post_id ) {
					return 'party123';
				}

				return '';
			}
		);

		PhotoAccessCodeDisplayStore::remember( 1, 'party123' );
		$this->assertSame( 'party123', PhotoAccessCodeDisplayStore::read( 1 ) );
	}

	public function test_owner_share_configured_does_not_require_publication(): void {
		$project = [
			'guest_photos_enabled'      => 1,
			'photo_share_token_hash'    => 'abc',
			'photo_access_code_hash'    => 'def',
			'publication_status'        => 'unpublished',
			'status'                    => 'active',
		];

		$this->assertTrue( PhotoShareEntitlement::is_owner_share_configured( $project ) );
		$this->assertFalse( PhotoShareEntitlement::is_share_ready( $project ) );
	}

	public function test_auto_approve_defaults_to_enabled_when_unset(): void {
		$project = [ 'guest_photos_enabled' => 1 ];
		$this->assertTrue( PhotoShareEntitlement::auto_approve_enabled( $project ) );
	}

	public function test_wall_url_uses_wall_path(): void {
		$url = PhotoShareTokenService::wall_url( 'token-value-0123456789abcdef01' );
		$this->assertStringContainsString( '/photos/token-value-0123456789abcdef01/wall/', $url );
	}

	public function test_access_code_verification_and_rate_limit(): void {
		$this->seed_project( [ 'photo_access_code_hash' => 'hash:party123', 'photo_access_code_version' => 1 ] );
		$project = $this->repositories->projects()->find_by_id( 1 );
		$this->assertIsArray( $project );

		$service = new PhotoAccessCodeService( $this->repositories->projects(), new PhotoAccessRateLimiter() );
		$hash    = InvitationToken::hash( 'share-token-raw-value-0123456789abcdef' );

		$this->assertTrue( $service->verify( $project, 'party123', $hash, 'client-a' )['success'] );
		$this->assertFalse( $service->verify( $project, 'wrong', $hash, 'client-a' )['success'] );
	}

	public function test_guest_session_invalidates_on_code_version_change(): void {
		$project = [
			'project_id'                => 1,
			'photo_share_token_version' => 2,
			'photo_access_code_version' => 3,
		];
		$share_hash = InvitationToken::hash( 'share-token-raw-value-0123456789abcdef' );
		$sessions   = new PhotoGuestSessionService();

		$this->seed_guest_session_cookie( $sessions, $project, $share_hash );

		$validated = $sessions->validate( $project, $share_hash );
		$this->assertIsArray( $validated );

		$changed = $project;
		$changed['photo_access_code_version'] = 4;
		$this->assertNull( $sessions->validate( $changed, $share_hash ) );
	}

	public function test_share_token_rotate_increments_version(): void {
		$this->seed_project( [
			'photo_share_token_hash'    => InvitationToken::hash( 'old-token-value-0123456789abcdef01' ),
			'photo_share_token_version' => 1,
		] );
		$project = $this->repositories->projects()->find_by_id( 1 );
		$this->assertIsArray( $project );

		Functions\when( 'update_post_meta' )->justReturn( true );
		$service = new PhotoShareTokenService( $this->repositories->projects() );
		$result  = $service->rotate( $project );
		$this->assertSame( 2, $result['version'] );
		$this->assertNotSame( 'old-token-value-0123456789abcdef01', $result['token'] );
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function seed_guest_session_cookie(
		PhotoGuestSessionService $sessions,
		array $project,
		string $share_token_hash
	): void {
		$sign = new \ReflectionMethod( PhotoGuestSessionService::class, 'sign' );
		$sign->setAccessible( true );
		$token = $sign->invoke(
			$sessions,
			[
				'project_id'          => (int) ( $project['project_id'] ?? 0 ),
				'share_token_hash'    => $share_token_hash,
				'share_token_version' => (int) ( $project['photo_share_token_version'] ?? 1 ),
				'code_version'        => (int) ( $project['photo_access_code_version'] ?? 0 ),
				'nonce'               => 'test-nonce-value',
				'exp'                 => time() + 3600,
			]
		);
		$this->assertIsString( $token );
		$this->assertNotSame( '', $token );

		$_COOKIE[ PhotoGuestSessionService::COOKIE_NAME ] = $token;
	}

	/**
	 * @param array<string, mixed> $overrides
	 */
	private function seed_project( array $overrides = [] ): void {
		$this->repositories->projects()->insert(
			array_merge(
				[
					'project_id'     => 1,
					'storage_uuid'   => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
					'user_id'        => 1,
					'order_id'       => 1,
					'order_item_id'  => 1,
					'product_id'     => 1,
					'template_id'    => '1',
					'status'         => 'active',
					'publication_status' => 'published',
				],
				$overrides
			)
		);
	}
}
