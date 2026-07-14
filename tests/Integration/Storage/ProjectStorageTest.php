<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Storage;

use PrikOgStreg\OnlineInvitations\Domain\Project\EnvelopeSnapshot;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageChecksumException;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageConflictException;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StoragePathException;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageValidationException;
use PrikOgStreg\OnlineInvitations\Storage\FileStreamResponse;
use PrikOgStreg\OnlineInvitations\Storage\StorageLimits;
use PrikOgStreg\OnlineInvitations\Storage\StorageRegistry;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class ProjectStorageTest extends TestCase {

	private string $root;

	private StorageRegistry $storage;

	private string $uuid = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';

	protected function setUp(): void {
		parent::setUp();

		$this->root    = sys_get_temp_dir() . '/pks-oi-storage-' . uniqid( '', true );
		$this->storage = new StorageRegistry( $this->root );
	}

	protected function tearDown(): void {
		$this->storage->project_storage()->delete_project_storage( $this->uuid );
		@rmdir( $this->root );
		parent::tearDown();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function base_context( int $expected_version = 0 ): array {
		return [
			'project_id'             => 101,
			'storage_uuid'           => $this->uuid,
			'builder_schema_version' => '1',
			'product_id'             => 12,
			'template_id'            => 'classic',
			'expected_state_version' => $expected_version,
			'state_json'             => '{"schema_version":"1","pages":[]}',
			'pages'                  => [
				[
					'index' => 1,
					'html'  => '<section>Page one</section>',
				],
			],
		];
	}

	public function test_create_project_directories(): void {
		$this->storage->project_storage()->create_project_directories( $this->uuid );

		$project_root = $this->root . '/projects/' . $this->uuid;
		$this->assertDirectoryExists( $project_root . '/state' );
		$this->assertDirectoryExists( $project_root . '/pages/editable' );
		$this->assertDirectoryExists( $project_root . '/tmp' );
	}

	public function test_atomic_save_success(): void {
		$result = $this->storage->project_storage()->save_state( $this->base_context( 0 ) );

		$this->assertSame( 1, $result['state_version'] );
		$this->assertSame( 'manifest.json', $result['state_manifest_path'] );
		$this->assertFileExists( $this->root . '/projects/' . $this->uuid . '/state/current.json' );
		$this->assertFileExists( $this->root . '/projects/' . $this->uuid . '/pages/editable/page-001.html' );
	}

	public function test_stale_version_conflict(): void {
		$this->storage->project_storage()->save_state( $this->base_context( 0 ) );

		$this->expectException( StorageConflictException::class );
		$this->storage->project_storage()->save_state( $this->base_context( 0 ) );
	}

	public function test_previous_state_recovery_after_successful_save(): void {
		$this->storage->project_storage()->save_state( $this->base_context( 0 ) );

		$context = $this->base_context( 1 );
		$context['state_json'] = '{"schema_version":"1","pages":["updated"]}';
		$this->storage->project_storage()->save_state( $context );

		$previous = $this->storage->project_storage()->read_previous_state( $this->uuid );
		$this->assertStringContainsString( '"pages":[]', (string) $previous );
	}

	public function test_checksum_mismatch_is_detected(): void {
		$this->storage->project_storage()->save_state( $this->base_context( 0 ) );

		$state_file = $this->root . '/projects/' . $this->uuid . '/state/current.json';
		file_put_contents( $state_file, '{"tampered":true}' );

		$this->expectException( StorageChecksumException::class );
		$this->storage->project_storage()->read_current_state( $this->uuid, true );
	}

	public function test_invalid_utf8_is_rejected(): void {
		$context               = $this->base_context( 0 );
		$context['state_json'] = "\xC3\x28";

		$this->expectException( StorageValidationException::class );
		$this->storage->project_storage()->save_state( $context );
	}

	public function test_oversized_state_is_rejected(): void {
		$context               = $this->base_context( 0 );
		$context['state_json'] = str_repeat( 'a', StorageLimits::MAX_STATE_BYTES + 1 );

		$this->expectException( StorageValidationException::class );
		$this->storage->project_storage()->save_state( $context );
	}

	public function test_publish_writes_separate_published_files(): void {
		$this->storage->project_storage()->save_state( $this->base_context( 0 ) );

		$result = $this->storage->project_storage()->publish_snapshot(
			[
				'project_id'             => 101,
				'storage_uuid'           => $this->uuid,
				'builder_schema_version' => '1',
				'product_id'             => 12,
				'template_id'            => 'classic',
				'expected_state_version' => 1,
				'published_version'      => 1,
				'pages'                  => [
					[
						'index' => 1,
						'html'  => '<section>Published</section>',
					],
				],
			]
		);

		$this->assertSame( 1, $result['published_version'] );
		$this->assertFileExists( $this->root . '/projects/' . $this->uuid . '/pages/published/page-001.html' );
		$this->assertFileExists( $this->root . '/projects/' . $this->uuid . '/published/manifest.json' );
	}

	public function test_stream_helper_does_not_return_public_url(): void {
		$this->storage->project_storage()->save_state( $this->base_context( 0 ) );
		$manifest = $this->storage->project_storage()->read_state_manifest( $this->uuid );
		$page     = $manifest->pages[0];

		$stream = $this->storage->file_streams()->open_relative(
			$this->uuid,
			(string) $page['editable_path'],
			'text/html',
			(string) $page['editable_sha256']
		);

		$this->assertStringStartsWith( $this->root, $stream->absolute_path );
		$this->assertGreaterThan( 0, $stream->byte_size );
		$stream->close();
	}

	public function test_deletion_is_idempotent(): void {
		$this->storage->project_storage()->save_state( $this->base_context( 0 ) );

		$this->assertTrue( $this->storage->project_storage()->delete_project_storage( $this->uuid ) );
		$this->assertTrue( $this->storage->project_storage()->delete_project_storage( $this->uuid ) );
	}

	public function test_storage_diagnostic_reports_health(): void {
		$this->storage->project_storage()->save_state( $this->base_context( 0 ) );
		$this->storage->project_storage()->import_envelope_snapshot(
			[
				'project_id'   => 101,
				'storage_uuid' => $this->uuid,
			],
			EnvelopeSnapshot::from_project_row(
				[
					'project_id'         => 101,
					'storage_uuid'       => $this->uuid,
					'product_id'         => 12,
					'envelope_preset'    => 'classic',
					'background_preset'  => 'neutral',
					'envelope_image_id'  => 0,
				]
			)
		);

		$report = $this->storage->diagnostic()->diagnose_project(
			[
				'storage_uuid'          => $this->uuid,
				'state_manifest_path'   => 'manifest.json',
				'state_version'         => 1,
				'published_manifest_path' => null,
			]
		);

		$this->assertTrue( $report['healthy'] );
		$this->assertTrue( $report['checksums_valid'] );
	}

	public function test_path_traversal_in_relative_path_is_rejected(): void {
		$this->expectException( StoragePathException::class );
		$this->storage->paths()->absolute_from_relative( $this->uuid, 'pages/editable/../../secret.txt' );
	}
}
