<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Public;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Guest\RsvpStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicEntitlement;
use PrikOgStreg\OnlineInvitations\Public\Endpoints;
use PrikOgStreg\OnlineInvitations\Public\EnvelopeViewModel;
use PrikOgStreg\OnlineInvitations\Public\PosterDimensions;
use PrikOgStreg\OnlineInvitations\Public\PosterDisplayAssets;
use PrikOgStreg\OnlineInvitations\Public\PublicInvitationContent;
use PrikOgStreg\OnlineInvitations\Public\PublicInvitationLoader;
use PrikOgStreg\OnlineInvitations\Public\PublishedPosterAssetSnapshotter;
use PrikOgStreg\OnlineInvitations\Public\TokenResolver;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;
use PrikOgStreg\OnlineInvitations\Storage\EnvelopeManifest;
use PrikOgStreg\OnlineInvitations\Storage\StorageRegistry;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeBuilderAdapter;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class PublicPosterExperienceTest extends TestCase {

	private string $storage_root;

	private FakeWpdb $wpdb;

	private RepositoryRegistry $repositories;

	private PublicInvitationLoader $loader;

	private StorageRegistry $storage_registry;

	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 ) . '/stubs/bpp/Builder_Adapter_Interface.php';

		$this->storage_root     = sys_get_temp_dir() . '/pks-oi-poster-' . uniqid( '', true );
		$this->wpdb             = new FakeWpdb();
		$this->repositories     = new RepositoryRegistry( $this->wpdb );
		$this->storage_registry = new StorageRegistry( $this->storage_root );
		$adapter                = new FakeBuilderAdapter();

		Functions\when( 'has_filter' )->justReturn( true );
		Functions\when( 'apply_filters' )->justReturn( $adapter );

		$builder = new BuilderService();
		$builder->resolve();

		$this->loader = new PublicInvitationLoader(
			$this->storage_registry->project_storage(),
			$builder,
			new PosterDisplayAssets( $this->storage_registry->project_storage() )
		);
	}

	protected function tearDown(): void {
		$this->delete_tree( $this->storage_root );
		parent::tearDown();
	}

	public function test_unpublished_project_fails_entitlement(): void {
		$token   = InvitationToken::generate();
		$project = $this->seed_project( null, $token['hash'], PublicationStatus::UNPUBLISHED );

		$this->assertFalse( PublicEntitlement::is_publicly_available( $project ) );
	}

	public function test_published_snapshot_renders_structured_pages(): void {
		$token   = InvitationToken::generate();
		$project = $this->seed_project( null, $token['hash'] );

		$result = $this->loader->load_published_content( $project );
		$this->assertTrue( $result['success'] ?? false );
		$this->assertInstanceOf( PublicInvitationContent::class, $result['content'] ?? null );
		$this->assertSame( 1, $result['content']->page_count );
		$this->assertStringContainsString( 'Published invitation', $result['content']->first_page_html() );
	}

	public function test_envelope_view_model_prefers_project_snapshot_manifest(): void {
		$token   = InvitationToken::generate();
		$project = $this->seed_project( null, $token['hash'], PublicationStatus::PUBLISHED, 'classic', 'neutral' );
		$this->write_envelope_manifest(
			(string) $project['storage_uuid'],
			[
				'preset'            => 'modern',
				'background_preset' => 'floral',
				'media_storage'     => EnvelopeManifest::MEDIA_NONE,
			]
		);

		$this->repositories->projects()->update(
			(int) $project['project_id'],
			[
				'envelope_preset'   => 'classic',
				'background_preset' => 'neutral',
			]
		);

		$updated = $this->repositories->projects()->find_by_id( (int) $project['project_id'] );
		$this->assertIsArray( $updated );

		$content = new PublicInvitationContent(
			[ [ 'index' => 1, 'html' => '<section>Published</section>' ] ],
			PosterDimensions::resolve( 'a5', 'flat' ),
			1
		);

		$resolution = ( new TokenResolver( $this->repositories->guests(), $this->repositories->projects() ) )->resolve( $token['raw'] );
		$this->assertNotNull( $resolution );

		$view = EnvelopeViewModel::from_resolution(
			$resolution,
			$content,
			$token['raw'],
			[],
			[],
			null,
			$this->storage_registry->project_storage()
		);

		$this->assertSame( 'modern', $view->envelope_preset );
		$this->assertSame( 'floral', $view->background_preset );
	}

	public function test_product_meta_changes_do_not_override_envelope_snapshot(): void {
		$token   = InvitationToken::generate();
		$project = $this->seed_project( null, $token['hash'] );
		$this->write_envelope_manifest(
			(string) $project['storage_uuid'],
			[
				'preset'            => 'minimal',
				'background_preset' => 'geometric',
				'media_storage'     => EnvelopeManifest::MEDIA_NONE,
			]
		);

		$this->repositories->projects()->update(
			(int) $project['project_id'],
			[
				'envelope_preset'   => 'classic',
				'background_preset' => 'neutral',
			]
		);

		$updated = $this->repositories->projects()->find_by_id( (int) $project['project_id'] );
		$this->assertIsArray( $updated );

		$content = new PublicInvitationContent(
			[ [ 'index' => 1, 'html' => '<section>Published</section>' ] ],
			PosterDimensions::resolve( 'a5', 'flat' ),
			1
		);
		$resolution = ( new TokenResolver( $this->repositories->guests(), $this->repositories->projects() ) )->resolve( $token['raw'] );
		$this->assertNotNull( $resolution );

		$view = EnvelopeViewModel::from_resolution(
			$resolution,
			$content,
			'',
			[],
			[],
			null,
			$this->storage_registry->project_storage()
		);

		$this->assertSame( 'minimal', $view->envelope_preset );
		$this->assertSame( 'geometric', $view->background_preset );
	}

	public function test_script_injection_is_blocked_at_load(): void {
		$token   = InvitationToken::generate();
		$project = $this->seed_project( null, $token['hash'], PublicationStatus::PUBLISHED, 'classic', 'neutral', '<section>Safe</section><iframe src="https://evil.test"></iframe>' );

		$result = $this->loader->load_published_content( $project );
		$this->assertFalse( $result['success'] ?? true );
		$this->assertSame( 'published_html_unsafe', $result['error'] ?? null );
	}

	public function test_missing_pages_returns_error(): void {
		$token   = InvitationToken::generate();
		$project = $this->seed_project( null, $token['hash'] );

		$manifest_path = $this->storage_root . '/projects/' . $project['storage_uuid'] . '/published/manifest.json';
		$raw           = (string) file_get_contents( $manifest_path );
		$data          = json_decode( $raw, true );
		$this->assertIsArray( $data );
		$data['pages'] = [];
		file_put_contents( $manifest_path, (string) json_encode( $data ) );

		$result = $this->loader->load_published_content( $project );
		$this->assertFalse( $result['success'] ?? true );
		$this->assertSame( 'missing_pages', $result['error'] ?? null );
	}

	public function test_multiple_pages_preserve_order_and_count(): void {
		$token   = InvitationToken::generate();
		$project = $this->seed_project(
			null,
			$token['hash'],
			PublicationStatus::PUBLISHED,
			'classic',
			'neutral',
			'<section>Page one</section>',
			5001,
			[
				[ 'index' => 1, 'html' => '<section>Page one</section>' ],
				[ 'index' => 2, 'html' => '<section>Page two</section>' ],
			]
		);

		$result = $this->loader->load_published_content( $project );
		$this->assertTrue( $result['success'] ?? false );
		$this->assertSame( 2, $result['content']->page_count );
		$this->assertSame( 1, $result['content']->pages[0]['index'] );
		$this->assertSame( 2, $result['content']->pages[1]['index'] );
		$this->assertStringContainsString( 'Page two', $result['content']->pages[1]['html'] );
	}

	public function test_landscape_dimensions_swap_for_landscape_format(): void {
		$dimensions = PosterDimensions::resolve( 'a5', 'landscape' );
		$this->assertSame( 'landscape', $dimensions['orientation'] );
		$this->assertGreaterThan( $dimensions['height'], $dimensions['width'] );
	}

	public function test_portrait_dimensions_from_inline_html(): void {
		$html       = '<div class="customizer-page-content" style="width:420px;height:595px">X</div>';
		$dimensions = PosterDimensions::resolve( 'a5', 'flat', $html );
		$this->assertSame( 420, $dimensions['width'] );
		$this->assertSame( 595, $dimensions['height'] );
		$this->assertSame( 'portrait', $dimensions['orientation'] );
	}

	public function test_poster_assets_snapshot_without_pdf_plugin(): void {
		$token   = InvitationToken::generate();
		$project = $this->seed_project( null, $token['hash'] );
		$storage = $this->storage_registry->project_storage();

		( new PublishedPosterAssetSnapshotter( $storage ) )->snapshot(
			$project,
			[
				'size'   => 'a5',
				'format' => 'flat',
				'page'   => [ '<section>Published invitation</section>' ],
			],
			[
				[ 'index' => 1, 'html' => '<section>Published invitation</section>' ],
			]
		);

		$manifest = $storage->try_read_poster_manifest( (string) $project['storage_uuid'] );
		$this->assertNotNull( $manifest );
		$this->assertSame( 510, $manifest->design_width );
		$this->assertSame( 680, $manifest->design_height );
		$this->assertNotNull( $manifest->display_css_path );

		$display_css = $storage->read_published_asset( (string) $project['storage_uuid'], (string) $manifest->display_css_path );
		$this->assertStringContainsString( 'pks-oi-poster-viewport', $display_css );

		$assets = new PosterDisplayAssets( $storage );
		$meta   = $assets->resolve_poster_meta( $project );
		$this->assertSame( 510, $meta['width'] );
	}

	public function test_generic_and_personal_tokens_both_resolve(): void {
		$guest_token   = InvitationToken::generate();
		$generic_token = InvitationToken::generate();
		$project       = $this->seed_project( $guest_token['hash'], $generic_token['hash'] );

		$this->repositories->guests()->insert(
			[
				'project_id'   => (int) $project['project_id'],
				'display_name' => 'Guest Name',
				'token_hash'   => $guest_token['hash'],
				'rsvp_status'  => RsvpStatus::PENDING,
			]
		);

		$resolver = new TokenResolver( $this->repositories->guests(), $this->repositories->projects() );

		$personal = $resolver->resolve( $guest_token['raw'] );
		$this->assertNotNull( $personal );
		$this->assertTrue( $personal->is_personal() );

		$generic = $resolver->resolve( $generic_token['raw'] );
		$this->assertNotNull( $generic );
		$this->assertTrue( $generic->is_generic() );
	}

	public function test_poster_asset_urls_are_token_scoped(): void {
		$source = (string) file_get_contents( PKS_OI_PLUGIN_PATH . 'src/Public/Endpoints.php' );
		$this->assertStringContainsString( '/poster-asset/(display|fonts)/', $source );
		$this->assertStringContainsString( 'poster_asset_url', $source );
	}

	/**
	 * @param list<array{index:int,html:string}>|null $pages
	 * @return array<string, mixed>
	 */
	private function seed_project(
		?string $guest_hash,
		?string $generic_hash,
		string $publication_status = PublicationStatus::PUBLISHED,
		string $envelope_preset = 'classic',
		string $background_preset = 'neutral',
		string $page_html = '<section>Published invitation</section>',
		int $project_id = 5001,
		?array $pages = null
	): array {
		$uuid = 5001 === $project_id
			? 'cccccccc-cccc-4ccc-8ccc-cccccccccccc'
			: 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';

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
				'event_title'             => 'Poster party',
				'envelope_preset'         => $envelope_preset,
				'background_preset'       => $background_preset,
				'generic_token_hash'      => $generic_hash,
				'state_version'           => 1,
				'published_manifest_path' => 'published/manifest.json',
			]
		);

		$storage  = $this->storage_registry->project_storage();
		$pages    = $pages ?? [ [ 'index' => 1, 'html' => $page_html ] ];

		$storage->save_state(
			[
				'project_id'             => $project_id,
				'storage_uuid'           => $uuid,
				'builder_schema_version' => '1',
				'product_id'             => 10,
				'template_id'            => '10',
				'expected_state_version' => 0,
				'state_json'             => '{"schema_version":"1","pages":[]}',
				'pages'                  => $pages,
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
				'pages'                  => $pages,
			]
		);

		$project = $this->repositories->projects()->find_by_id( $project_id );
		$this->assertIsArray( $project );

		return $project;
	}

	/**
	 * @param array<string, mixed> $overrides
	 */
	private function write_envelope_manifest( string $storage_uuid, array $overrides ): void {
		$payload = array_merge(
			[
				'schema_version'     => EnvelopeManifest::SCHEMA_VERSION,
				'project_id'         => 5001,
				'storage_uuid'       => $storage_uuid,
				'source_product_id'  => 10,
				'preset'             => 'classic',
				'background_preset'  => 'neutral',
				'configuration_type' => 'preset_only',
				'attachment_id'      => 0,
				'media_storage'      => EnvelopeManifest::MEDIA_NONE,
				'snapshotted_at_utc' => gmdate( 'Y-m-d H:i:s' ),
			],
			$overrides
		);

		$dir = $this->storage_root . '/projects/' . $storage_uuid . '/envelope';
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0777, true );
		}

		file_put_contents( $dir . '/manifest.json', (string) json_encode( $payload ) );
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
