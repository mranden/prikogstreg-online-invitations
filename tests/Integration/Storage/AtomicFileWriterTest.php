<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Storage;

use PrikOgStreg\OnlineInvitations\Storage\AtomicFileWriter;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageException;
use PrikOgStreg\OnlineInvitations\Storage\StorageDirectory;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class AtomicFileWriterTest extends TestCase {

	public function test_atomic_write_succeeds_with_checksum(): void {
		$root = sys_get_temp_dir() . '/pks-oi-atomic-' . uniqid( '', true );
		StorageDirectory::ensure( $root );

		$writer = new AtomicFileWriter();
		$target = $root . '/sample.json';
		$data   = '{"ok":true}';

		$checksum = $writer->write( $target, $data );

		$this->assertFileExists( $target );
		$this->assertSame( hash( 'sha256', $data ), $checksum );
		$this->assertSame( $data, file_get_contents( $target ) );

		@unlink( $target );
		@rmdir( $root );
	}

	public function test_simulated_partial_failure_does_not_replace_target(): void {
		$root = sys_get_temp_dir() . '/pks-oi-atomic-fail-' . uniqid( '', true );
		StorageDirectory::ensure( $root );

		$target  = $root . '/current.json';
		$initial = '{"version":1}';
		file_put_contents( $target, $initial );

		$writer = new AtomicFileWriter();
		$writer->set_after_temp_write_hook(
			static function (): void {
				throw new StorageException( 'Simulated failure before rename.' );
			}
		);

		try {
			$writer->write( $target, '{"version":2}' );
			$this->fail( 'Expected StorageException was not thrown.' );
		} catch ( StorageException ) {
			$this->assertSame( $initial, file_get_contents( $target ) );
		}

		@unlink( $target );
		@rmdir( $root );
	}
}
