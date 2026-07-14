<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\Domain\Project;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStateService;
use PrikOgStreg\OnlineInvitations\Storage\StorageRegistry;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeBuilderAdapter;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class ProjectStateServicePublishTest extends TestCase {

	private string $storage_root;

	private ProjectStateService $service;

	private FakeBuilderAdapter $adapter;

	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 3 ) . '/stubs/bpp/Builder_Adapter_Interface.php';

		$this->storage_root = sys_get_temp_dir() . '/pks-oi-state-pub-' . uniqid( '', true );
		$this->adapter      = new FakeBuilderAdapter();

		Functions\when( 'has_filter' )->justReturn( true );
		Functions\when( 'apply_filters' )->justReturn( $this->adapter );

		$builder = new BuilderService();
		$builder->resolve();

		$storage = ( new StorageRegistry( $this->storage_root ) )->project_storage();
		$this->service = new ProjectStateService(
			$builder,
			$storage,
			( new RepositoryRegistry( new FakeWpdb() ) )->projects(),
			( new RepositoryRegistry( new FakeWpdb() ) )->events()
		);
	}

	protected function tearDown(): void {
		$this->delete_tree( $this->storage_root );
		parent::tearDown();
	}

	public function test_adapter_context_sets_is_public_for_publish_mode(): void {
		$context = $this->service->adapter_context(
			[
				'project_id'    => 1,
				'product_id'    => 10,
				'user_id'       => 2,
				'state_version' => 1,
			],
			'public'
		);

		$this->assertTrue( $context['is_public'] );
		$this->assertFalse( $context['is_preview'] );
	}

	public function test_load_state_for_publish_merges_editable_pages_when_adapter_state_empty(): void {
		$uuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
		$storage = ( new StorageRegistry( $this->storage_root ) )->project_storage();

		$storage->save_state(
			[
				'project_id'             => 1,
				'storage_uuid'           => $uuid,
				'builder_schema_version' => '1',
				'product_id'             => 10,
				'template_id'            => '10',
				'expected_state_version' => 0,
				'state_json'             => '{"schema_version":"1","pages":[{"index":1}]}',
				'pages'                  => [
					[ 'index' => 1, 'html' => '<section>Editable page body</section>' ],
				],
			]
		);

		$this->adapter->with_load_state(
			[
				'schema_version' => '1',
				'page'           => [],
				'field'          => [],
			]
		);

		$state = $this->service->load_state_for_publish(
			[
				'project_id'    => 1,
				'storage_uuid'  => $uuid,
				'product_id'    => 10,
				'state_version' => 1,
			]
		);

		$this->assertIsArray( $state['page'] ?? null );
		$this->assertStringContainsString( 'Editable page body', (string) ( $state['page'][0] ?? '' ) );
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
