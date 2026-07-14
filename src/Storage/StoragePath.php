<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Storage;

use PrikOgStreg\OnlineInvitations\Storage\Exception\StoragePathException;

/**
 * Resolves private storage paths from storage_uuid and controlled relative paths only.
 */
final class StoragePath {

	public const FALLBACK_SUBDIR = 'pks-oi-private';
	public const PROJECTS_DIR    = 'projects';

	public const STATE_CURRENT          = 'state/current.json';
	public const STATE_PREVIOUS         = 'state/previous.json';
	public const STATE_MANIFEST         = 'manifest.json';
	public const PUBLISHED_MANIFEST     = 'published/manifest.json';
	public const EDITABLE_PAGE_PATTERN  = 'pages/editable/page-%03d.html';
	public const PUBLISHED_PAGE_PATTERN = 'pages/published/page-%03d.html';

	/** @var list<string> */
	private const ALLOWED_PREFIXES = [
		'state/',
		'pages/editable/',
		'pages/published/',
		'published/',
		'previews/',
		'wishlist/images/',
		'photos/pending/',
		'photos/approved/',
		'photos/thumbnails/',
		'tmp/',
	];

	public function __construct(
		private ?string $root_override = null
	) {}

	public function root(): string {
		if ( null !== $this->root_override && '' !== $this->root_override ) {
			return rtrim( $this->root_override, '/\\' );
		}

		if ( defined( 'PKS_OI_STORAGE_PATH' ) && is_string( PKS_OI_STORAGE_PATH ) && '' !== PKS_OI_STORAGE_PATH ) {
			return rtrim( PKS_OI_STORAGE_PATH, '/\\' );
		}

		if ( defined( 'WP_CONTENT_DIR' ) ) {
			return rtrim( WP_CONTENT_DIR, '/\\' ) . '/uploads/' . self::FALLBACK_SUBDIR;
		}

		return rtrim( sys_get_temp_dir(), '/\\' ) . '/pks-oi-private';
	}

	public function uses_fallback_root(): bool {
		return ! ( defined( 'PKS_OI_STORAGE_PATH' ) && is_string( PKS_OI_STORAGE_PATH ) && '' !== PKS_OI_STORAGE_PATH );
	}

	public function projects_root(): string {
		return $this->root() . '/' . self::PROJECTS_DIR;
	}

	public function project_root( string $storage_uuid ): string {
		$this->assert_storage_uuid( $storage_uuid );

		return $this->projects_root() . '/' . $storage_uuid;
	}

	public function project_tmp_dir( string $storage_uuid ): string {
		return $this->project_root( $storage_uuid ) . '/tmp';
	}

	public function absolute_from_relative( string $storage_uuid, string $relative_path ): string {
		$this->assert_relative_path( $relative_path );

		$project_root = $this->project_root( $storage_uuid );
		$absolute     = $project_root . '/' . $relative_path;
		$resolved     = $this->normalize_absolute( $absolute );

		if ( ! str_starts_with( $resolved, $this->normalize_absolute( $project_root ) . '/' ) && $resolved !== $this->normalize_absolute( $project_root ) ) {
			throw new StoragePathException( 'Resolved path escapes project root.' );
		}

		return $resolved;
	}

	public function editable_page_path( int $index ): string {
		if ( $index < 1 || $index > 999 ) {
			throw new StoragePathException( 'Page index out of range.' );
		}

		return sprintf( self::EDITABLE_PAGE_PATTERN, $index );
	}

	public function published_page_path( int $index ): string {
		if ( $index < 1 || $index > 999 ) {
			throw new StoragePathException( 'Page index out of range.' );
		}

		return sprintf( self::PUBLISHED_PAGE_PATTERN, $index );
	}

	public function assert_storage_uuid( string $storage_uuid ): void {
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $storage_uuid ) ) {
			throw new StoragePathException( 'Invalid storage UUID.' );
		}
	}

	public function assert_relative_path( string $relative_path ): void {
		$relative_path = ltrim( str_replace( '\\', '/', $relative_path ), '/' );

		if ( '' === $relative_path ) {
			throw new StoragePathException( 'Relative path is empty.' );
		}

		if ( str_contains( $relative_path, '..' ) || str_starts_with( $relative_path, '/' ) ) {
			throw new StoragePathException( 'Directory traversal is not allowed.' );
		}

		if ( ! preg_match( '/^[a-zA-Z0-9._\\/\\-]+$/', $relative_path ) ) {
			throw new StoragePathException( 'Relative path contains invalid characters.' );
		}

		if ( self::STATE_MANIFEST === $relative_path || self::PUBLISHED_MANIFEST === $relative_path ) {
			return;
		}

		foreach ( self::ALLOWED_PREFIXES as $prefix ) {
			if ( str_starts_with( $relative_path, $prefix ) ) {
				return;
			}
		}

		throw new StoragePathException( 'Relative path is not allowlisted.' );
	}

	private function normalize_absolute( string $path ): string {
		$parts  = [];
		$pieces = explode( '/', str_replace( '\\', '/', $path ) );

		foreach ( $pieces as $piece ) {
			if ( '' === $piece || '.' === $piece ) {
				continue;
			}

			if ( '..' === $piece ) {
				array_pop( $parts );
				continue;
			}

			$parts[] = $piece;
		}

		$normalized = implode( '/', $parts );

		if ( str_starts_with( $path, '/' ) ) {
			return '/' . $normalized;
		}

		return $normalized;
	}
}
