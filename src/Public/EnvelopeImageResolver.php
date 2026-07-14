<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

use PrikOgStreg\OnlineInvitations\Storage\EnvelopeManifest;
use PrikOgStreg\OnlineInvitations\Storage\ProjectStorage;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\AttachmentValidator;

/**
 * Resolves envelope card image URLs from project-owned snapshot storage.
 */
final class EnvelopeImageResolver {

	public function __construct(
		private ProjectStorage $storage
	) {}

	/**
	 * @param array<string, mixed> $project
	 */
	public function resolve_url( array $project, string $invitation_token = '' ): string {
		$storage_uuid = (string) ( $project['storage_uuid'] ?? '' );
		if ( '' !== $storage_uuid ) {
			$manifest = $this->storage->try_read_envelope_manifest( $storage_uuid );
			if ( $manifest instanceof EnvelopeManifest ) {
				if ( EnvelopeManifest::MEDIA_PROJECT_COPY === $manifest->media_storage && '' !== $invitation_token ) {
					$url = Endpoints::envelope_image_url( $invitation_token );
					if ( '' !== $url ) {
						return $url;
					}
				}

				if ( $manifest->attachment_id > 0 ) {
					$url = AttachmentValidator::image_url( $manifest->attachment_id );
					if ( '' !== $url ) {
						return $url;
					}
				}
			}
		}

		$attachment_id = max( 0, (int) ( $project['envelope_image_id'] ?? 0 ) );

		return AttachmentValidator::image_url( $attachment_id );
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array{width:int,height:int}
	 */
	public function resolve_dimensions( array $project ): array {
		$storage_uuid = (string) ( $project['storage_uuid'] ?? '' );
		if ( '' !== $storage_uuid ) {
			$manifest = $this->storage->try_read_envelope_manifest( $storage_uuid );
			if ( $manifest instanceof EnvelopeManifest && EnvelopeManifest::MEDIA_PROJECT_COPY === $manifest->media_storage ) {
				$path = $this->storage->envelope_image_absolute_path( $storage_uuid );
				if ( is_string( $path ) && is_readable( $path ) && function_exists( 'getimagesize' ) ) {
					$size = getimagesize( $path );
					if ( is_array( $size ) && isset( $size[0], $size[1] ) ) {
						return [
							'width'  => max( 1, (int) $size[0] ),
							'height' => max( 1, (int) $size[1] ),
						];
					}
				}
			}
		}

		$attachment_id = max( 0, (int) ( $project['envelope_image_id'] ?? 0 ) );
		if ( $attachment_id > 0 && function_exists( 'wp_get_attachment_metadata' ) ) {
			$meta = wp_get_attachment_metadata( $attachment_id );
			if ( is_array( $meta ) && isset( $meta['width'], $meta['height'] ) ) {
				return [
					'width'  => max( 1, (int) $meta['width'] ),
					'height' => max( 1, (int) $meta['height'] ),
				];
			}
		}

		return [
			'width'  => 0,
			'height' => 0,
		];
	}
}
