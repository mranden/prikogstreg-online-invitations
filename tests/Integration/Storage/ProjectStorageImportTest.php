<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Storage;

use PrikOgStreg\OnlineInvitations\Storage\StorageRegistry;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class ProjectStorageImportTest extends TestCase {

	private string $root;

	private string $uuid = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';

	protected function setUp(): void {
		parent::setUp();
		$this->root = sys_get_temp_dir() . '/pks-oi-import-' . uniqid( '', true );
	}

	protected function tearDown(): void {
		( new StorageRegistry( $this->root ) )->project_storage()->delete_project_storage( $this->uuid );
		@rmdir( $this->root );
		parent::tearDown();
	}

	public function test_import_from_builder_state_writes_state_and_pages(): void {
		$storage = ( new StorageRegistry( $this->root ) )->project_storage();
		$result  = $storage->import_from_builder_state(
			[
				'project_id'             => 101,
				'storage_uuid'           => $this->uuid,
				'builder_schema_version' => '1',
				'product_id'             => 10,
				'template_id'            => '10',
			],
			[
				'field'          => [ 'uuid-1' => [ 'text' => 'Hello' ] ],
				'page'           => [ '<section>Imported</section>' ],
				'size'           => 'a5',
				'format'         => 'flat',
				'schema_version' => '1',
			]
		);

		$this->assertSame( 1, $result['state_version'] );
		$this->assertFileExists( $this->root . '/projects/' . $this->uuid . '/state/current.json' );
		$this->assertFileExists( $this->root . '/projects/' . $this->uuid . '/pages/editable/page-001.html' );
	}
}
