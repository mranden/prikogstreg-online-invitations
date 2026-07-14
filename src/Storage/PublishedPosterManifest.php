<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Storage;

use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageException;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageValidationException;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

/**
 * Read/write published/poster-manifest.json — poster display metadata snapshotted at publish.
 */
final class PublishedPosterManifest {

	public const RELATIVE_PATH   = 'published/poster-manifest.json';
	public const DISPLAY_CSS     = 'published/poster-display.css';
	public const FONTS_CSS       = 'published/poster-fonts.css';
	public const SCHEMA_VERSION  = '1';
	public const FILTER_DISPLAY  = 'pks_oi/capture_poster_display_css';
	public const FILTER_FONTS    = 'pks_oi/capture_poster_fonts_css';

	public function __construct(
		public readonly string $schema_version,
		public readonly int $page_count,
		public readonly string $size,
		public readonly string $format,
		public readonly string $orientation,
		public readonly int $design_width,
		public readonly int $design_height,
		public readonly ?string $display_css_path,
		public readonly ?string $display_css_sha256,
		public readonly ?string $fonts_css_path,
		public readonly ?string $fonts_css_sha256,
		public readonly string $snapshotted_at_utc
	) {}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function from_array( array $data ): self {
		return new self(
			(string) ( $data['schema_version'] ?? self::SCHEMA_VERSION ),
			max( 1, (int) ( $data['page_count'] ?? 1 ) ),
			sanitize_title( (string) ( $data['size'] ?? 'a5' ) ),
			sanitize_title( (string) ( $data['format'] ?? 'flat' ) ),
			sanitize_key( (string) ( $data['orientation'] ?? 'portrait' ) ),
			max( 1, (int) ( $data['design_width'] ?? 510 ) ),
			max( 1, (int) ( $data['design_height'] ?? 680 ) ),
			isset( $data['display_css_path'] ) ? (string) $data['display_css_path'] : null,
			isset( $data['display_css_sha256'] ) ? (string) $data['display_css_sha256'] : null,
			isset( $data['fonts_css_path'] ) ? (string) $data['fonts_css_path'] : null,
			isset( $data['fonts_css_sha256'] ) ? (string) $data['fonts_css_sha256'] : null,
			(string) ( $data['snapshotted_at_utc'] ?? UtcDateTime::now() )
		);
	}

	public function to_json(): string {
		$payload = [
			'schema_version'    => $this->schema_version,
			'page_count'        => $this->page_count,
			'size'              => $this->size,
			'format'            => $this->format,
			'orientation'       => $this->orientation,
			'design_width'      => $this->design_width,
			'design_height'     => $this->design_height,
			'snapshotted_at_utc' => $this->snapshotted_at_utc,
		];

		if ( null !== $this->display_css_path && '' !== $this->display_css_path ) {
			$payload['display_css_path'] = $this->display_css_path;
		}

		if ( null !== $this->display_css_sha256 && '' !== $this->display_css_sha256 ) {
			$payload['display_css_sha256'] = $this->display_css_sha256;
		}

		if ( null !== $this->fonts_css_path && '' !== $this->fonts_css_path ) {
			$payload['fonts_css_path'] = $this->fonts_css_path;
		}

		if ( null !== $this->fonts_css_sha256 && '' !== $this->fonts_css_sha256 ) {
			$payload['fonts_css_sha256'] = $this->fonts_css_sha256;
		}

		$json = json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR );

		if ( strlen( $json ) > StorageLimits::MAX_MANIFEST_BYTES ) {
			throw new StorageValidationException( 'Poster manifest exceeds maximum size.' );
		}

		return is_string( $json ) ? $json : '';
	}

	public static function read_file( string $absolute_path ): self {
		if ( ! is_readable( $absolute_path ) ) {
			throw new StorageException( 'Poster manifest is not readable.' );
		}

		$raw = file_get_contents( $absolute_path );
		if ( false === $raw ) {
			throw new StorageException( 'Poster manifest could not be read.' );
		}

		if ( strlen( $raw ) > StorageLimits::MAX_MANIFEST_BYTES ) {
			throw new StorageValidationException( 'Poster manifest exceeds maximum size.' );
		}

		$data = json_decode( $raw, true, 512, JSON_THROW_ON_ERROR );
		if ( ! is_array( $data ) ) {
			throw new StorageValidationException( 'Poster manifest JSON is invalid.' );
		}

		return self::from_array( $data );
	}
}
