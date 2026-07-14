<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Storage;

use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageException;

/**
 * Directory creation helpers for private storage.
 */
final class StorageDirectory {

	public static function ensure( string $path, int $permissions = 0750 ): void {
		if ( is_dir( $path ) ) {
			return;
		}

		if ( function_exists( 'wp_mkdir_p' ) ) {
			if ( ! wp_mkdir_p( $path ) ) {
				throw new StorageException( 'Could not create directory: ' . $path );
			}

			return;
		}

		if ( ! mkdir( $path, $permissions, true ) && ! is_dir( $path ) ) {
			throw new StorageException( 'Could not create directory: ' . $path );
		}
	}
}
