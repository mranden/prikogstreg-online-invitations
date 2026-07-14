<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Storage;

use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageException;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageValidationException;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

/**
 * Read/write project manifest.json and published/manifest.json.
 */
final class ProjectManifest {

	public const RELATIVE_STATE_PATH     = StoragePath::STATE_MANIFEST;
	public const RELATIVE_PUBLISHED_PATH = StoragePath::PUBLISHED_MANIFEST;

	/**
	 * @param list<array<string, mixed>> $pages
	 */
	public function __construct(
		public readonly int $project_id,
		public readonly string $storage_uuid,
		public readonly string $builder_schema_version,
		public readonly int $state_version,
		public readonly int $product_id,
		public readonly string $template_id,
		public readonly array $pages,
		public readonly string $updated_at_utc,
		public readonly ?int $published_version = null,
		public readonly ?string $state_sha256 = null
	) {}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function from_array( array $data ): self {
		return new self(
			(int) ( $data['project_id'] ?? 0 ),
			(string) ( $data['storage_uuid'] ?? '' ),
			(string) ( $data['builder_schema_version'] ?? '1' ),
			(int) ( $data['state_version'] ?? 1 ),
			(int) ( $data['product_id'] ?? 0 ),
			(string) ( $data['template_id'] ?? '' ),
			is_array( $data['pages'] ?? null ) ? $data['pages'] : [],
			(string) ( $data['updated_at_utc'] ?? UtcDateTime::now() ),
			isset( $data['published_version'] ) ? (int) $data['published_version'] : null,
			isset( $data['state_sha256'] ) ? (string) $data['state_sha256'] : null
		);
	}

	public function to_json(): string {
		$payload = [
			'project_id'             => $this->project_id,
			'storage_uuid'           => $this->storage_uuid,
			'builder_schema_version' => $this->builder_schema_version,
			'state_version'          => $this->state_version,
			'product_id'             => $this->product_id,
			'template_id'            => $this->template_id,
			'pages'                  => $this->pages,
			'updated_at_utc'         => $this->updated_at_utc,
			'state_path'             => StoragePath::STATE_CURRENT,
		];

		if ( null !== $this->state_sha256 ) {
			$payload['state_sha256'] = $this->state_sha256;
		}

		if ( null !== $this->published_version ) {
			$payload['published_version'] = $this->published_version;
		}

		$json = self::encode_json( $payload );

		if ( strlen( $json ) > StorageLimits::MAX_MANIFEST_BYTES ) {
			throw new StorageValidationException( 'Manifest exceeds maximum size.' );
		}

		return $json;
	}

	public static function read_file( string $absolute_path ): self {
		if ( ! is_readable( $absolute_path ) ) {
			throw new StorageException( 'Manifest is not readable.' );
		}

		$raw = file_get_contents( $absolute_path );
		if ( false === $raw ) {
			throw new StorageException( 'Manifest could not be read.' );
		}

		if ( strlen( $raw ) > StorageLimits::MAX_MANIFEST_BYTES ) {
			throw new StorageValidationException( 'Manifest exceeds maximum size.' );
		}

		$data = json_decode( $raw, true, 512, JSON_THROW_ON_ERROR );
		if ( ! is_array( $data ) ) {
			throw new StorageValidationException( 'Manifest JSON is invalid.' );
		}

		return self::from_array( $data );
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private static function encode_json( array $payload ): string {
		$json = json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR );

		return is_string( $json ) ? $json : '';
	}
}
