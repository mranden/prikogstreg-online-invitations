<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\Storage;

use PrikOgStreg\OnlineInvitations\Storage\Exception\StoragePathException;
use PrikOgStreg\OnlineInvitations\Storage\StoragePath;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class StoragePathTest extends TestCase {

	private StoragePath $paths;

	protected function setUp(): void {
		parent::setUp();
		$this->paths = new StoragePath( sys_get_temp_dir() . '/pks-oi-path-test' );
	}

	public function test_rejects_directory_traversal(): void {
		$this->expectException( StoragePathException::class );
		$this->paths->absolute_from_relative( 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', '../secret.txt' );
	}

	public function test_rejects_invalid_storage_uuid(): void {
		$this->expectException( StoragePathException::class );
		$this->paths->project_root( 'not-a-uuid' );
	}

	public function test_accepts_allowlisted_relative_paths(): void {
		$uuid = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
		$path = $this->paths->absolute_from_relative( $uuid, StoragePath::STATE_CURRENT );
		$this->assertStringEndsWith( '/projects/' . $uuid . '/state/current.json', $path );
	}
}
