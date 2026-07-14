<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Photo;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoCleanupService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoImageValidator;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoLimits;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoModerationStatus;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoServiceFactory;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;
use PrikOgStreg\OnlineInvitations\Public\TokenResolution;
use PrikOgStreg\OnlineInvitations\Public\TokenResolver;
use PrikOgStreg\OnlineInvitations\Security\Authorization;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;
use PrikOgStreg\OnlineInvitations\Storage\StorageRegistry;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\Support\PhotoFixtures;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class PhotoTest extends TestCase {

	private FakeWpdb $wpdb;

	private RepositoryRegistry $repositories;

	private StorageRegistry $storage;

	private \PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoService $photos;

	private TokenResolver $resolver;

	private string $storage_root;

	protected function setUp(): void {
		parent::setUp();

		$this->wpdb         = new FakeWpdb();
		$this->repositories = new RepositoryRegistry( $this->wpdb );
		$this->storage_root = sys_get_temp_dir() . '/pks-oi-photo-test-' . uniqid( '', true );
		$this->storage      = new StorageRegistry( $this->storage_root );
		$this->photos       = PhotoServiceFactory::create( $this->repositories, $this->storage );
		$this->resolver     = new TokenResolver( $this->repositories->guests(), $this->repositories->projects() );

		Functions\when( 'do_action' )->justReturn( null );
	}

	protected function tearDown(): void {
		$uuid = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
		$this->storage->project_storage()->delete_project_storage( $uuid );
		@rmdir( $this->storage_root );
		parent::tearDown();
	}

	public function test_valid_png_upload_and_moderation(): void {
		$project = $this->seed_project();
		$token   = InvitationToken::generate();
		$this->seed_guest( (int) $project['project_id'], $token );
		$resolution = $this->resolver->resolve( $token['raw'] );
		$this->assertInstanceOf( TokenResolution::class, $resolution );

		$intent = $this->photos->issue_intent( $resolution, $token['raw'] );
		$this->assertTrue( $intent['success'] );

		$file   = PhotoFixtures::file_from_bytes( PhotoFixtures::png_1x1(), 'party.png' );
		$result = $this->photos->upload( $resolution, $token['raw'], (string) $intent['intent'], [ $file ] );
		$this->assertTrue( $result['success'] );
		$photo_id = (int) ( $result['uploaded'][0]['photo_id'] ?? 0 );
		$this->assertGreaterThan( 0, $photo_id );

		$approve = $this->photos->moderate( $project, $photo_id, 'approve' );
		$this->assertTrue( $approve['success'] );

		$row = $this->repositories->photos()->find_by_id( $photo_id );
		$this->assertIsArray( $row );
		$this->assertSame( PhotoModerationStatus::APPROVED, $row['moderation_status'] ?? '' );
		$this->assertStringStartsWith( 'photos/approved/', (string) ( $row['relative_path'] ?? '' ) );
	}

	public function test_jpeg_and_webp_validation(): void {
		$validator = new PhotoImageValidator();
		$jpeg      = $validator->validate_bytes( PhotoFixtures::jpeg_1x1() );
		$this->assertTrue( $jpeg['success'] );
		$webp = $validator->validate_bytes( PhotoFixtures::webp_1x1() );
		$this->assertTrue( $webp['success'] );
	}

	public function test_svg_and_mime_spoof_rejected(): void {
		$validator = new PhotoImageValidator();
		$this->assertFalse( $validator->validate_bytes( PhotoFixtures::svg() )['success'] ?? true );
		$this->assertFalse( $validator->validate_bytes( PhotoFixtures::fake_jpeg_header() )['success'] ?? true );
	}

	public function test_oversized_bytes_rejected(): void {
		$validator = new PhotoImageValidator();
		$bytes     = PhotoFixtures::png_1x1() . str_repeat( 'x', PhotoLimits::MAX_FILE_BYTES );
		$result    = $validator->validate_bytes( $bytes );
		$this->assertFalse( $result['success'] ?? true );
		$this->assertSame( 'file_too_large', $result['error'] ?? '' );
	}

	public function test_expired_intent_rejected(): void {
		$project = $this->seed_project();
		$token   = InvitationToken::generate();
		$guest_id = $this->seed_guest( (int) $project['project_id'], $token );
		$resolution = $this->resolver->resolve( $token['raw'] );
		$this->assertInstanceOf( TokenResolution::class, $resolution );

		$payload = wp_json_encode(
			[
				'project_id' => (int) $project['project_id'],
				'guest_id'   => $guest_id,
				'token_hash' => $token['hash'],
				'exp'        => time() - 60,
				'max_files'  => PhotoLimits::MAX_FILES_PER_REQUEST,
				'nonce'      => 'expired-test',
			]
		);
		$this->assertIsString( $payload );
		$encoded = rtrim( strtr( base64_encode( $payload ), '+/', '-_' ), '=' );
		$intent  = $encoded . '.' . hash_hmac( 'sha256', $encoded, wp_salt( 'pks_oi_photo_upload' ) );

		$file   = PhotoFixtures::file_from_bytes( PhotoFixtures::png_1x1() );
		$result = $this->photos->upload( $resolution, $token['raw'], $intent, [ $file ] );
		$this->assertFalse( $result['success'] ?? true );
		$this->assertSame( 'expired_intent', $result['error'] ?? '' );
	}

	public function test_wrong_token_rejected(): void {
		$project = $this->seed_project();
		$token   = InvitationToken::generate();
		$other   = InvitationToken::generate();
		$this->seed_guest( (int) $project['project_id'], $token );
		$resolution = $this->resolver->resolve( $token['raw'] );
		$this->assertInstanceOf( TokenResolution::class, $resolution );

		$intent = $this->photos->issue_intent( $resolution, $token['raw'] );
		$file   = PhotoFixtures::file_from_bytes( PhotoFixtures::png_1x1() );
		$result = $this->photos->upload( $resolution, $other['raw'], (string) $intent['intent'], [ $file ] );
		$this->assertFalse( $result['success'] ?? true );
		$this->assertSame( 'wrong_token', $result['error'] ?? '' );
	}

	public function test_intent_rate_limit(): void {
		$project = $this->seed_project();
		$token   = InvitationToken::generate();
		$this->seed_guest( (int) $project['project_id'], $token );
		$resolution = $this->resolver->resolve( $token['raw'] );
		$this->assertInstanceOf( TokenResolution::class, $resolution );

		for ( $i = 0; $i < PhotoLimits::INTENT_RATE_MAX; ++$i ) {
			$result = $this->photos->issue_intent( $resolution, $token['raw'] );
			$this->assertTrue( $result['success'] );
		}

		$result = $this->photos->issue_intent( $resolution, $token['raw'] );
		$this->assertFalse( $result['success'] ?? true );
		$this->assertSame( 'rate_limited', $result['error'] ?? '' );
	}

	public function test_traversal_filename_sanitized(): void {
		$project = $this->seed_project();
		$token   = InvitationToken::generate();
		$this->seed_guest( (int) $project['project_id'], $token );
		$resolution = $this->resolver->resolve( $token['raw'] );
		$this->assertInstanceOf( TokenResolution::class, $resolution );
		$intent = $this->photos->issue_intent( $resolution, $token['raw'] );

		$file   = PhotoFixtures::file_from_bytes( PhotoFixtures::png_1x1(), '../../etc/passwd.png' );
		$result = $this->photos->upload( $resolution, $token['raw'], (string) $intent['intent'], [ $file ] );
		$this->assertTrue( $result['success'] );

		$photo_id = (int) ( $result['uploaded'][0]['photo_id'] ?? 0 );
		$row      = $this->repositories->photos()->find_by_id( $photo_id );
		$this->assertSame( 'passwd.png', $row['original_filename'] ?? '' );
	}

	public function test_download_authorization(): void {
		$project = $this->seed_project();
		$auth    = new Authorization( $this->repositories->projects() );

		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		Functions\when( 'current_user_can' )->justReturn( true );

		$denied = $this->photos->resolve_download( $project, 999, $auth );
		$this->assertFalse( $denied['success'] ?? true );

		$token = InvitationToken::generate();
		$this->seed_guest( (int) $project['project_id'], $token );
		$resolution = $this->resolver->resolve( $token['raw'] );
		$this->assertInstanceOf( TokenResolution::class, $resolution );
		$intent = $this->photos->issue_intent( $resolution, $token['raw'] );
		$file   = PhotoFixtures::file_from_bytes( PhotoFixtures::png_1x1() );
		$upload = $this->photos->upload( $resolution, $token['raw'], (string) $intent['intent'], [ $file ] );
		$photo_id = (int) ( $upload['uploaded'][0]['photo_id'] ?? 0 );

		$allowed = $this->photos->resolve_download( $project, $photo_id, $auth );
		$this->assertTrue( $allowed['success'] );
	}

	public function test_cleanup_orphan_pending_files(): void {
		$project = $this->seed_project();
		$uuid    = (string) $project['storage_uuid'];
		$this->storage->project_storage()->create_project_directories( $uuid );
		$dir = $this->storage_root . '/projects/' . $uuid . '/photos/pending';
		$orphan = $dir . '/orphan-' . uniqid( '', true ) . '.png';
		file_put_contents( $orphan, PhotoFixtures::png_1x1() );
		touch( $orphan, time() - 7200 );

		$cleanup = new PhotoCleanupService( $this->storage->paths(), $this->repositories->photos() );
		$removed = $cleanup->cleanup_orphan_pending( $uuid, 3600 );
		$this->assertGreaterThanOrEqual( 1, $removed );
		$this->assertFileDoesNotExist( $orphan );
	}

	public function test_guest_erasure_deletes_photos(): void {
		$project = $this->seed_project();
		$token   = InvitationToken::generate();
		$guest_id = $this->seed_guest( (int) $project['project_id'], $token );
		$resolution = $this->resolver->resolve( $token['raw'] );
		$this->assertInstanceOf( TokenResolution::class, $resolution );
		$intent = $this->photos->issue_intent( $resolution, $token['raw'] );
		$file   = PhotoFixtures::file_from_bytes( PhotoFixtures::png_1x1() );
		$upload = $this->photos->upload( $resolution, $token['raw'], (string) $intent['intent'], [ $file ] );
		$photo_id = (int) ( $upload['uploaded'][0]['photo_id'] ?? 0 );

		$deleted = $this->photos->erase_guest_photos( $guest_id );
		$this->assertSame( 1, $deleted );

		$row = $this->repositories->photos()->find_by_id( $photo_id );
		$this->assertNotNull( $row['deleted_at_utc'] ?? null );
	}

	/**
	 * @param array<string, mixed> $overrides
	 * @return array<string, mixed>
	 */
	private function seed_project( array $overrides = [] ): array {
		$this->repositories->projects()->insert(
			array_merge(
				[
					'project_id'           => 6001,
					'storage_uuid'         => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
					'user_id'              => 7,
					'order_id'             => 100,
					'order_item_id'        => 601,
					'product_id'           => 10,
					'template_id'          => '10',
					'status'               => ProjectStatus::ACTIVE,
					'publication_status'   => PublicationStatus::PUBLISHED,
					'state_version'        => 1,
					'guest_photos_enabled' => 1,
					'published_manifest_path' => '/tmp/manifest.json',
				],
				$overrides
			)
		);

		$project = $this->repositories->projects()->find_by_id( 6001 );
		$this->assertIsArray( $project );

		return $project;
	}

	/**
	 * @param array{raw:string,hash:string} $token
	 */
	private function seed_guest( int $project_id, array $token ): int {
		return $this->repositories->guests()->insert(
			[
				'project_id'   => $project_id,
				'display_name' => 'Photo Guest',
				'email'        => 'photo@example.com',
				'token_hash'   => $token['hash'],
			]
		);
	}
}
