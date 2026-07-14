<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

use PrikOgStreg\OnlineInvitations\Storage\ProjectStorage;
use PrikOgStreg\OnlineInvitations\Storage\PublishedPosterManifest;

/**
 * Enqueues project-owned poster display CSS/fonts on public invitation routes.
 */
final class PosterDisplayAssets {

	public function __construct(
		private ProjectStorage $storage
	) {}

	/**
	 * @param array<string, mixed> $project
	 */
	public function enqueue( array $project, string $raw_token ): void {
		$storage_uuid = (string) ( $project['storage_uuid'] ?? '' );
		if ( '' === $storage_uuid ) {
			return;
		}

		$manifest = $this->storage->try_read_poster_manifest( $storage_uuid );
		if ( null === $manifest ) {
			$this->enqueue_runtime_fallback( $project );

			return;
		}

		if ( null !== $manifest->display_css_path && '' !== $manifest->display_css_path && '' !== $raw_token ) {
			$url = Endpoints::poster_asset_url( $raw_token, 'display' );
			if ( '' !== $url ) {
				wp_enqueue_style( 'pks-oi-poster-display', $url, [ 'pks-oi-public' ], PKS_OI_VERSION );
			}
		}

		if ( null !== $manifest->fonts_css_path && '' !== $manifest->fonts_css_path && '' !== $raw_token ) {
			$url = Endpoints::poster_asset_url( $raw_token, 'fonts' );
			if ( '' !== $url ) {
				wp_enqueue_style( 'pks-oi-poster-fonts', $url, [ 'pks-oi-public' ], PKS_OI_VERSION );
			}
		}
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function enqueue_runtime_fallback( array $project ): void {
		$adapter = apply_filters( 'bpp/integration/service', null );
		if ( null !== $adapter && method_exists( $adapter, 'enqueue_public_assets' ) ) {
			$adapter->enqueue_public_assets(
				[
					'product_id'  => (int) ( $project['product_id'] ?? 0 ),
					'template_id' => (int) ( $project['product_id'] ?? 0 ),
					'locale'      => (string) ( $project['locale'] ?? 'da_DK' ),
					'mode'        => 'public',
					'is_public'   => true,
				]
			);

			return;
		}

		if ( defined( 'BPP_PLUGIN_URLS' ) && is_string( BPP_PLUGIN_URLS ) ) {
			$url = rtrim( BPP_PLUGIN_URLS, '/\\' ) . '/dist/css/public.css';
			wp_enqueue_style( 'bpp-public-css', $url, [ 'pks-oi-public' ], null );
		}

		if ( function_exists( 'BPP_fonts_css' ) ) {
			$fonts = BPP_fonts_css();
			if ( is_string( $fonts ) && '' !== trim( $fonts ) ) {
				wp_register_style( 'pks-oi-poster-fonts-runtime', false, [ 'pks-oi-public' ], PKS_OI_VERSION );
				wp_enqueue_style( 'pks-oi-poster-fonts-runtime' );
				wp_add_inline_style( 'pks-oi-poster-fonts-runtime', $fonts );
			}
		}
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public function resolve_poster_meta( array $project, string $sample_html = '' ): array {
		$storage_uuid = (string) ( $project['storage_uuid'] ?? '' );
		if ( '' !== $storage_uuid ) {
			$manifest = $this->storage->try_read_poster_manifest( $storage_uuid );
			if ( $manifest instanceof PublishedPosterManifest ) {
				return [
					'width'       => $manifest->design_width,
					'height'      => $manifest->design_height,
					'orientation' => $manifest->orientation,
					'size'        => $manifest->size,
					'format'      => $manifest->format,
				];
			}
		}

		return PosterDimensions::resolve( 'a5', 'flat', $sample_html );
	}
}
