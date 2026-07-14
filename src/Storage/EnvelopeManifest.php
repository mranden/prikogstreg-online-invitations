<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Storage;

use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageException;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageValidationException;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

/**
 * Read/write envelope/manifest.json — immutable envelope configuration snapshot.
 */
final class EnvelopeManifest {

	public const RELATIVE_PATH    = 'envelope/manifest.json';
	public const SCHEMA_VERSION   = '1';
	public const MEDIA_NONE         = 'none';
	public const MEDIA_PROJECT_COPY = 'project_copy';
	public const MEDIA_ATTACHMENT   = 'attachment_reference';

	/**
	 * @param array<string, mixed> $data
	 */
	public function __construct(
		public readonly int $project_id,
		public readonly string $storage_uuid,
		public readonly string $schema_version,
		public readonly int $source_product_id,
		public readonly string $preset,
		public readonly string $background_preset,
		public readonly string $configuration_type,
		public readonly int $attachment_id,
		public readonly string $media_storage,
		public readonly ?string $image_path,
		public readonly ?string $image_sha256,
		public readonly string $snapshotted_at_utc
	) {}

	/**
	 * @param array<string, mixed> $snapshot Normalized envelope snapshot from EnvelopeSnapshot.
	 */
	public static function from_snapshot( array $snapshot ): self {
		$attachment_id = max( 0, (int) ( $snapshot['attachment_id'] ?? 0 ) );
		$preset          = sanitize_key( (string) ( $snapshot['preset'] ?? '' ) );
		$background      = sanitize_key( (string) ( $snapshot['background_preset'] ?? '' ) );

		$configuration_type = $attachment_id > 0 ? 'preset_with_image' : 'preset_only';
		$media_storage    = (string) ( $snapshot['media_storage'] ?? self::MEDIA_NONE );
		if ( ! in_array( $media_storage, [ self::MEDIA_NONE, self::MEDIA_PROJECT_COPY, self::MEDIA_ATTACHMENT ], true ) ) {
			$media_storage = self::MEDIA_NONE;
		}

		return new self(
			(int) ( $snapshot['project_id'] ?? 0 ),
			(string) ( $snapshot['storage_uuid'] ?? '' ),
			self::SCHEMA_VERSION,
			(int) ( $snapshot['source_product_id'] ?? 0 ),
			$preset,
			$background,
			$configuration_type,
			$attachment_id,
			$media_storage,
			isset( $snapshot['image_path'] ) ? (string) $snapshot['image_path'] : null,
			isset( $snapshot['image_sha256'] ) ? (string) $snapshot['image_sha256'] : null,
			(string) ( $snapshot['snapshotted_at_utc'] ?? UtcDateTime::now() )
		);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function from_array( array $data ): self {
		return new self(
			(int) ( $data['project_id'] ?? 0 ),
			(string) ( $data['storage_uuid'] ?? '' ),
			(string) ( $data['schema_version'] ?? self::SCHEMA_VERSION ),
			(int) ( $data['source_product_id'] ?? 0 ),
			sanitize_key( (string) ( $data['preset'] ?? '' ) ),
			sanitize_key( (string) ( $data['background_preset'] ?? '' ) ),
			(string) ( $data['configuration_type'] ?? 'preset_only' ),
			max( 0, (int) ( $data['attachment_id'] ?? 0 ) ),
			(string) ( $data['media_storage'] ?? self::MEDIA_NONE ),
			isset( $data['image_path'] ) ? (string) $data['image_path'] : null,
			isset( $data['image_sha256'] ) ? (string) $data['image_sha256'] : null,
			(string) ( $data['snapshotted_at_utc'] ?? UtcDateTime::now() )
		);
	}

	public function to_json(): string {
		$payload = [
			'schema_version'       => $this->schema_version,
			'project_id'           => $this->project_id,
			'storage_uuid'         => $this->storage_uuid,
			'source_product_id'    => $this->source_product_id,
			'preset'               => $this->preset,
			'background_preset'    => $this->background_preset,
			'configuration_type'   => $this->configuration_type,
			'attachment_id'        => $this->attachment_id,
			'media_storage'        => $this->media_storage,
			'snapshotted_at_utc'   => $this->snapshotted_at_utc,
		];

		if ( null !== $this->image_path && '' !== $this->image_path ) {
			$payload['image_path'] = $this->image_path;
		}

		if ( null !== $this->image_sha256 && '' !== $this->image_sha256 ) {
			$payload['image_sha256'] = $this->image_sha256;
		}

		$json = json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR );

		if ( strlen( $json ) > StorageLimits::MAX_MANIFEST_BYTES ) {
			throw new StorageValidationException( 'Envelope manifest exceeds maximum size.' );
		}

		return is_string( $json ) ? $json : '';
	}

	public static function read_file( string $absolute_path ): self {
		if ( ! is_readable( $absolute_path ) ) {
			throw new StorageException( 'Envelope manifest is not readable.' );
		}

		$raw = file_get_contents( $absolute_path );
		if ( false === $raw ) {
			throw new StorageException( 'Envelope manifest could not be read.' );
		}

		if ( strlen( $raw ) > StorageLimits::MAX_MANIFEST_BYTES ) {
			throw new StorageValidationException( 'Envelope manifest exceeds maximum size.' );
		}

		$data = json_decode( $raw, true, 512, JSON_THROW_ON_ERROR );
		if ( ! is_array( $data ) ) {
			throw new StorageValidationException( 'Envelope manifest JSON is invalid.' );
		}

		return self::from_array( $data );
	}
}
