<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Public;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestTokenService;
use PrikOgStreg\OnlineInvitations\Domain\Guest\RsvpStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicEntitlement;
use PrikOgStreg\OnlineInvitations\Public\EnvelopeViewModel;
use PrikOgStreg\OnlineInvitations\Public\OpenTracker;
use PrikOgStreg\OnlineInvitations\Public\PosterDisplayAssets;
use PrikOgStreg\OnlineInvitations\Public\PublicInvitationContent;
use PrikOgStreg\OnlineInvitations\Public\PublicInvitationLoader;
use PrikOgStreg\OnlineInvitations\Public\TokenResolution;
use PrikOgStreg\OnlineInvitations\Public\TokenResolver;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;
use PrikOgStreg\OnlineInvitations\Storage\StorageRegistry;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeBuilderAdapter;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class PublicInvitationTest extends TestCase {

	private string $storage_root;

	private FakeWpdb $wpdb;

	private RepositoryRegistry $repositories;

	private TokenResolver $resolver;

	private PublicInvitationLoader $loader;

	private OpenTracker $open_tracker;

	private FakeBuilderAdapter $adapter;

	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 ) . '/stubs/bpp/Builder_Adapter_Interface.php';

		$this->storage_root = sys_get_temp_dir() . '/pks-oi-public-' . uniqid( '', true );
		$this->wpdb         = new FakeWpdb();
		$this->repositories = new RepositoryRegistry( $this->wpdb );
		$this->adapter      = new FakeBuilderAdapter();

		Functions\when( 'has_filter' )->justReturn( true );
		Functions\when( 'apply_filters' )->justReturn( $this->adapter );
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'get_current_user_id' )->justReturn( 0 );

		$builder = new BuilderService();
		$builder->resolve();

		$this->resolver     = new TokenResolver( $this->repositories->guests(), $this->repositories->projects() );
		$this->loader       = new PublicInvitationLoader(
			( new StorageRegistry( $this->storage_root ) )->project_storage(),
			$builder,
			new PosterDisplayAssets( ( new StorageRegistry( $this->storage_root ) )->project_storage() )
		);
		$this->open_tracker = new OpenTracker( $this->repositories->guests() );
	}

	protected function tearDown(): void {
		$this->delete_storage_tree( $this->storage_root );
		parent::tearDown();
	}

	public function test_valid_personal_token_resolves_guest_first(): void {
		$guest_token = InvitationToken::generate();
		$project     = $this->seed_published_project( $guest_token['hash'], null );

		$this->repositories->guests()->insert(
			[
				'project_id'   => (int) $project['project_id'],
				'display_name' => 'Ada Lovelace',
				'token_hash'   => $guest_token['hash'],
				'rsvp_status'  => RsvpStatus::PENDING,
			]
		);

		$resolution = $this->resolver->resolve( $guest_token['raw'] );
		$this->assertInstanceOf( TokenResolution::class, $resolution );
		$this->assertTrue( $resolution->is_personal() );
		$this->assertSame( 'Ada Lovelace', $resolution->guest()['display_name'] ?? '' );
	}

	public function test_valid_generic_token_resolves_project(): void {
		$generic_token = InvitationToken::generate();
		$this->seed_published_project( null, $generic_token['hash'] );

		$resolution = $this->resolver->resolve( $generic_token['raw'] );
		$this->assertInstanceOf( TokenResolution::class, $resolution );
		$this->assertTrue( $resolution->is_generic() );
		$this->assertNull( $resolution->guest() );
	}

	public function test_invalid_token_returns_null(): void {
		$this->assertNull( $this->resolver->resolve( 'not-a-valid-token' ) );
		$this->assertNull( $this->resolver->resolve( 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' ) );
	}

	public function test_revoked_guest_token_returns_null(): void {
		$guest_token = InvitationToken::generate();
		$project     = $this->seed_published_project( $guest_token['hash'], null );

		$guest_id = $this->repositories->guests()->insert(
			[
				'project_id'   => (int) $project['project_id'],
				'display_name' => 'Revoked Guest',
				'token_hash'   => $guest_token['hash'],
				'rsvp_status'  => RsvpStatus::PENDING,
			]
		);

		$guest = $this->repositories->guests()->find_by_id( $guest_id );
		$this->assertIsArray( $guest );

		( new GuestTokenService( $this->repositories->guests() ) )->revoke( $guest );

		$this->assertNull( $this->resolver->resolve( $guest_token['raw'] ) );
	}

	public function test_unpublished_project_fails_public_entitlement(): void {
		$generic_token = InvitationToken::generate();
		$project       = $this->seed_published_project( null, $generic_token['hash'], PublicationStatus::UNPUBLISHED );

		$this->assertFalse( PublicEntitlement::is_publicly_available( $project ) );
	}

	public function test_restricted_project_fails_public_entitlement(): void {
		$generic_token = InvitationToken::generate();
		$project       = $this->seed_published_project( null, $generic_token['hash'] );
		$this->repositories->projects()->update(
			(int) $project['project_id'],
			[ 'restricted_at_utc' => gmdate( 'Y-m-d H:i:s' ) ]
		);
		$updated = $this->repositories->projects()->find_by_id( (int) $project['project_id'] );
		$this->assertIsArray( $updated );
		$this->assertFalse( PublicEntitlement::is_publicly_available( $updated ) );
	}

	public function test_expired_project_fails_public_entitlement(): void {
		$generic_token = InvitationToken::generate();
		$project       = $this->seed_published_project( null, $generic_token['hash'] );
		$this->repositories->projects()->update(
			(int) $project['project_id'],
			[ 'expires_at_utc' => '2020-01-01 00:00:00' ]
		);
		$updated = $this->repositories->projects()->find_by_id( (int) $project['project_id'] );
		$this->assertIsArray( $updated );
		$this->assertFalse( PublicEntitlement::is_publicly_available( $updated ) );
	}

	public function test_checksum_failure_blocks_published_load(): void {
		$generic_token = InvitationToken::generate();
		$project       = $this->seed_published_project( null, $generic_token['hash'] );

		$page_file = $this->storage_root . '/projects/' . $project['storage_uuid'] . '/pages/published/page-001.html';
		file_put_contents( $page_file, '<section>Tampered</section>' );

		$result = $this->loader->load_published_content( $project );
		$this->assertFalse( $result['success'] ?? true );
		$this->assertSame( 'checksum_failure', $result['error'] ?? null );
	}

	public function test_xss_fixture_is_not_returned_from_loader(): void {
		$generic_token = InvitationToken::generate();
		$project       = $this->seed_published_project( null, $generic_token['hash'], PublicationStatus::PUBLISHED, '<section>Safe</section><script>alert(1)</script>' );

		$result = $this->loader->load_published_content( $project );
		$this->assertFalse( $result['success'] ?? true );
		$this->assertSame( 'published_html_unsafe', $result['error'] ?? null );
	}

	public function test_view_model_does_not_expose_internal_ids(): void {
		$guest_token = InvitationToken::generate();
		$project     = $this->seed_published_project( $guest_token['hash'], null );

		$this->repositories->guests()->insert(
			[
				'project_id'   => (int) $project['project_id'],
				'display_name' => 'No IDs',
				'token_hash'   => $guest_token['hash'],
				'rsvp_status'  => RsvpStatus::PENDING,
			]
		);

		$resolution = $this->resolver->resolve( $guest_token['raw'] );
		$this->assertInstanceOf( TokenResolution::class, $resolution );

		$content = new PublicInvitationContent(
			[
				[ 'index' => 1, 'html' => '<section>Published</section>' ],
			],
			[
				'width'       => 510,
				'height'      => 680,
				'orientation' => 'portrait',
				'size'        => 'a5',
				'format'      => 'flat',
			],
			1
		);

		$view = EnvelopeViewModel::from_resolution( $resolution, $content );
		$export = json_encode(
			[
				'addressee_label' => $view->addressee_label,
				'link_type'       => $view->link_type,
				'event_title'     => $view->event_title,
				'html'            => $view->invitation_html,
			],
			JSON_UNESCAPED_SLASHES
		);

		$this->assertIsString( $export );
		$this->assertStringNotContainsString( (string) $project['project_id'], $export );
		$this->assertStringNotContainsString( (string) $project['user_id'], $export );
		$this->assertStringNotContainsString( (string) $project['order_id'], $export );
		$this->assertStringContainsString( 'No IDs', $export );
	}

	public function test_open_tracker_records_personal_open_only(): void {
		$guest_token = InvitationToken::generate();
		$project     = $this->seed_published_project( $guest_token['hash'], null );

		$guest_id = $this->repositories->guests()->insert(
			[
				'project_id'   => (int) $project['project_id'],
				'display_name' => 'Opener',
				'token_hash'   => $guest_token['hash'],
				'rsvp_status'  => RsvpStatus::PENDING,
			]
		);

		$resolution = $this->resolver->resolve( $guest_token['raw'] );
		$this->assertInstanceOf( TokenResolution::class, $resolution );

		$this->open_tracker->maybe_track( $resolution );

		$guest = $this->repositories->guests()->find_by_id( $guest_id );
		$this->assertNotSame( '', (string) ( $guest['first_opened_at_utc'] ?? '' ) );
		$this->assertSame( 1, (int) ( $guest['open_count'] ?? 0 ) );

		$generic_token = InvitationToken::generate();
		$generic_project = $this->seed_published_project( null, $generic_token['hash'], PublicationStatus::PUBLISHED, '<section>Generic</section>', 4002 );
		$generic_resolution = $this->resolver->resolve( $generic_token['raw'] );
		$this->assertInstanceOf( TokenResolution::class, $generic_resolution );

		$this->open_tracker->maybe_track( $generic_resolution );
		$this->assertSame( 1, (int) ( $this->repositories->guests()->find_by_id( $guest_id )['open_count'] ?? 0 ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function seed_published_project(
		?string $guest_hash,
		?string $generic_hash,
		string $publication_status = PublicationStatus::PUBLISHED,
		string $page_html = '<section>Published invitation</section>',
		int $project_id = 4001
	): array {
		$uuid = 4001 === $project_id
			? 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
			: 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';

		$this->repositories->projects()->insert(
			[
				'project_id'              => $project_id,
				'storage_uuid'            => $uuid,
				'user_id'                 => 7,
				'order_id'                => 100,
				'order_item_id'           => 500 + $project_id,
				'product_id'              => 10,
				'template_id'             => '10',
				'status'                  => ProjectStatus::ACTIVE,
				'publication_status'      => $publication_status,
				'event_title'             => 'Summer party',
				'envelope_preset'         => 'classic',
				'background_preset'       => 'neutral',
				'generic_token_hash'      => $generic_hash,
				'state_version'           => 1,
				'published_manifest_path' => 'published/manifest.json',
			]
		);

		$storage = ( new StorageRegistry( $this->storage_root ) )->project_storage();
		$storage->save_state(
			[
				'project_id'             => $project_id,
				'storage_uuid'           => $uuid,
				'builder_schema_version' => '1',
				'product_id'             => 10,
				'template_id'            => '10',
				'expected_state_version' => 0,
				'state_json'             => '{"schema_version":"1","pages":[]}',
				'pages'                  => [
					[ 'index' => 1, 'html' => $page_html ],
				],
			]
		);

		$storage->publish_snapshot(
			[
				'project_id'             => $project_id,
				'storage_uuid'           => $uuid,
				'builder_schema_version' => '1',
				'product_id'             => 10,
				'template_id'            => '10',
				'expected_state_version' => 1,
				'published_version'      => 1,
				'pages'                  => [
					[ 'index' => 1, 'html' => $page_html ],
				],
			]
		);

		$project = $this->repositories->projects()->find_by_id( $project_id );
		$this->assertIsArray( $project );

		return $project;
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
