<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Public;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;
use PrikOgStreg\OnlineInvitations\Public\PublicInvitationLoader;
use PrikOgStreg\OnlineInvitations\Storage\StorageRegistry;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeBuilderAdapter;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;
use Brain\Monkey\Functions;

final class PublicInvitationLoaderTest extends TestCase {

	private string $storage_root;

	private PublicInvitationLoader $loader;

	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 ) . '/stubs/bpp/Builder_Adapter_Interface.php';

		$this->storage_root = sys_get_temp_dir() . '/pks-oi-loader-' . uniqid( '', true );
		$adapter            = new FakeBuilderAdapter();

		Functions\when( 'has_filter' )->justReturn( true );
		Functions\when( 'apply_filters' )->justReturn( $adapter );

		$builder = new BuilderService();
		$builder->resolve();

		$this->loader = new PublicInvitationLoader(
			( new StorageRegistry( $this->storage_root ) )->project_storage(),
			$builder
		);
	}

	protected function tearDown(): void {
		$this->delete_tree( $this->storage_root );
		parent::tearDown();
	}

	public function test_draft_only_state_does_not_serve_published_content(): void {
		$uuid = 'ffffffff-ffff-4fff-8fff-ffffffffffff';
		$storage = ( new StorageRegistry( $this->storage_root ) )->project_storage();

		$storage->save_state(
			[
				'project_id'             => 5001,
				'storage_uuid'           => $uuid,
				'builder_schema_version' => '1',
				'product_id'             => 10,
				'template_id'            => '10',
				'expected_state_version' => 0,
				'state_json'             => '{"schema_version":"1"}',
				'pages'                  => [
					[ 'index' => 1, 'html' => '<section>Draft only</section>' ],
				],
			]
		);

		$project = [
			'project_id'              => 5001,
			'storage_uuid'            => $uuid,
			'publication_status'      => PublicationStatus::UNPUBLISHED,
			'status'                  => ProjectStatus::ACTIVE,
			'published_manifest_path' => 'published/manifest.json',
		];

		$result = $this->loader->load_published_content( $project );
		$this->assertFalse( $result['success'] ?? true );
		$this->assertContains( $result['error'] ?? '', [ 'manifest_missing', 'missing_pages' ] );
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
