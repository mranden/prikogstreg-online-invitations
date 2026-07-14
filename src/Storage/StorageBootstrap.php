<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Storage;

/**
 * Ensures protected fallback storage root when outside-web-root path is unavailable.
 */
final class StorageBootstrap {

	public function __construct(
		private StoragePath $paths
	) {}

	public function register(): void {
		add_action( 'init', [ $this, 'ensure_fallback_protection' ], 2 );
	}

	public function ensure_fallback_protection(): void {
		if ( ! $this->paths->uses_fallback_root() ) {
			return;
		}

		$root = $this->paths->root();
		StorageDirectory::ensure( $root );

		$htaccess = $root . '/.htaccess';
		if ( ! is_file( $htaccess ) ) {
			file_put_contents( $htaccess, "Deny from all\n" );
		}

		$index = $root . '/index.php';
		if ( ! is_file( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
	}
}
