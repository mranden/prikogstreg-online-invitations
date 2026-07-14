<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Security\PublishedHtmlSanitizer;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageChecksumException;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageException;
use PrikOgStreg\OnlineInvitations\Storage\ProjectStorage;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;

/**
 * Loads verified published invitation HTML for public display.
 */
final class PublicInvitationLoader {

	public function __construct(
		private ProjectStorage $storage,
		private BuilderService $builder
	) {}

	/**
	 * @param array<string, mixed> $project
	 * @return array{success:bool,html?:string,pages?:list<string>,error?:string}
	 */
	public function load_published_content( array $project ): array {
		$storage_uuid = (string) ( $project['storage_uuid'] ?? '' );
		if ( '' === $storage_uuid ) {
			return [ 'success' => false, 'error' => 'missing_storage' ];
		}

		try {
			$manifest = $this->storage->read_published_manifest( $storage_uuid, true );
		} catch ( StorageChecksumException $exception ) {
			return [ 'success' => false, 'error' => 'checksum_failure' ];
		} catch ( StorageException $exception ) {
			return [ 'success' => false, 'error' => 'manifest_missing' ];
		}

		$pages = [];
		foreach ( $manifest->pages as $page ) {
			if ( ! isset( $page['published_path'] ) ) {
				continue;
			}

			try {
				$html = $this->storage->read_published_page(
					$storage_uuid,
					(string) $page['published_path'],
					isset( $page['published_sha256'] ) ? (string) $page['published_sha256'] : null
				);
				$html = PublishedHtmlSanitizer::sanitize( $html );
			} catch ( StorageChecksumException $exception ) {
				return [ 'success' => false, 'error' => 'checksum_failure' ];
			} catch ( \InvalidArgumentException $exception ) {
				return [ 'success' => false, 'error' => 'published_html_unsafe' ];
			}

			$pages[] = $html;
		}

		if ( [] === $pages ) {
			return [ 'success' => false, 'error' => 'missing_pages' ];
		}

		$html = implode( "\n", $pages );
		$adapter = $this->builder->get_adapter();
		if ( null !== $adapter && method_exists( $adapter, 'wrap_public_html' ) ) {
			$wrapped = $adapter->wrap_public_html(
				$html,
				[
					'product_id'  => (int) ( $project['product_id'] ?? 0 ),
					'template_id' => (int) ( $project['product_id'] ?? 0 ),
					'locale'      => (string) ( $project['locale'] ?? ProductMeta::DEFAULT_LOCALE_VALUE ),
					'mode'        => 'public',
					'is_public'   => true,
				]
			);
			if ( is_string( $wrapped ) && '' !== $wrapped ) {
				$html = $wrapped;
			}
		}

		return [
			'success' => true,
			'html'    => $html,
			'pages'   => $pages,
		];
	}
}
