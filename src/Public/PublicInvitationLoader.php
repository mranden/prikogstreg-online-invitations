<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Security\PublishedHtmlSanitizer;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageChecksumException;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageException;
use PrikOgStreg\OnlineInvitations\Storage\ProjectStorage;
use PrikOgStreg\OnlineInvitations\Support\PublishedHtmlValidator;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;

/**
 * Loads verified published invitation HTML for public display.
 */
final class PublicInvitationLoader {

	public function __construct(
		private ProjectStorage $storage,
		private BuilderService $builder,
		private PosterDisplayAssets $poster_assets
	) {}

	/**
	 * @param array<string, mixed> $project
	 * @return array{success:bool,content?:PublicInvitationContent,html?:string,pages?:list<array{index:int,html:string}>,error?:string}
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

			$index = (int) ( $page['index'] ?? ( count( $pages ) + 1 ) );
			$wrapped = $this->wrap_page_html( $html, $project, $index, count( $manifest->pages ) );

			if ( ! PublishedHtmlValidator::has_visible_content( $wrapped ) ) {
				return [ 'success' => false, 'error' => 'empty_published_html' ];
			}

			$pages[] = [
				'index' => max( 1, $index ),
				'html'  => $wrapped,
			];
		}

		if ( [] === $pages ) {
			return [ 'success' => false, 'error' => 'missing_pages' ];
		}

		usort(
			$pages,
			static fn( array $left, array $right ): int => $left['index'] <=> $right['index']
		);

		$sample_html = (string) ( $pages[0]['html'] ?? '' );
		$poster      = $this->poster_assets->resolve_poster_meta( $project, $sample_html );

		$content = new PublicInvitationContent(
			$pages,
			$poster,
			count( $pages )
		);

		return [
			'success' => true,
			'content' => $content,
			'html'    => $content->first_page_html(),
			'pages'   => $pages,
		];
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function wrap_page_html( string $html, array $project, int $index, int $total ): string {
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
					'page_index'  => $index,
					'page_total'  => $total,
				]
			);
			if ( is_string( $wrapped ) && '' !== $wrapped ) {
				return $wrapped;
			}
		}

		return '<div class="bpp-public-page" data-page="' . esc_attr( (string) $index ) . '">' . $html . '</div>';
	}
}
