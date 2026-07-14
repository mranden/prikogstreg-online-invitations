<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

use PrikOgStreg\OnlineInvitations\Storage\PublishedPosterManifest;
use PrikOgStreg\OnlineInvitations\Storage\ProjectStorage;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

/**
 * Snapshots BPP display CSS and fonts at publish so public routes work without pdf-plugin.
 */
final class PublishedPosterAssetSnapshotter {

	public function __construct(
		private ProjectStorage $storage
	) {}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $state
	 * @param list<array{index:int,html:string}> $pages
	 */
	public function snapshot( array $project, array $state, array $pages ): void {
		$storage_uuid = (string) ( $project['storage_uuid'] ?? '' );
		if ( '' === $storage_uuid || [] === $pages ) {
			return;
		}

		$sample_html = (string) ( $pages[0]['html'] ?? '' );
		$size        = sanitize_title( (string) ( $state['size'] ?? 'a5' ) );
		$format      = sanitize_title( (string) ( $state['format'] ?? 'flat' ) );
		$dimensions  = PosterDimensions::resolve( $size, $format, $sample_html );

		$display_css = $this->resolve_display_css( $project, $state );
		$fonts_css   = $this->resolve_fonts_css( $project, $state );

		$display_path = null;
		$display_sha    = null;
		$fonts_path     = null;
		$fonts_sha      = null;

		if ( '' !== $display_css ) {
			$display_path = PublishedPosterManifest::DISPLAY_CSS;
			$display_sha  = $this->write_project_file( $storage_uuid, $display_path, $display_css );
		}

		if ( '' !== $fonts_css ) {
			$fonts_path = PublishedPosterManifest::FONTS_CSS;
			$fonts_sha  = $this->write_project_file( $storage_uuid, $fonts_path, $fonts_css );
		}

		$manifest = new PublishedPosterManifest(
			PublishedPosterManifest::SCHEMA_VERSION,
			count( $pages ),
			$dimensions['size'],
			$dimensions['format'],
			$dimensions['orientation'],
			$dimensions['width'],
			$dimensions['height'],
			$display_path,
			$display_sha,
			$fonts_path,
			$fonts_sha,
			UtcDateTime::now()
		);

		$this->write_project_file( $storage_uuid, PublishedPosterManifest::RELATIVE_PATH, $manifest->to_json() );
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $state
	 */
	private function resolve_display_css( array $project, array $state ): string {
		$context = [
			'product_id'  => (int) ( $project['product_id'] ?? 0 ),
			'template_id' => (string) ( $project['template_id'] ?? '' ),
			'locale'      => (string) ( $project['locale'] ?? 'da_DK' ),
			'mode'        => 'public',
			'is_public'   => true,
			'state'       => $state,
		];

		/**
		 * @var string $css
		 */
		$css = apply_filters( PublishedPosterManifest::FILTER_DISPLAY, '', $context );
		if ( is_string( $css ) && '' !== trim( $css ) ) {
			return $css;
		}

		if ( defined( 'BPP_PLUGIN_PATH' ) && is_string( BPP_PLUGIN_PATH ) ) {
			$path = rtrim( BPP_PLUGIN_PATH, '/\\' ) . '/dist/css/public.css';
			if ( is_readable( $path ) ) {
				$raw = file_get_contents( $path );
				if ( is_string( $raw ) && '' !== $raw ) {
					return $raw;
				}
			}
		}

		$fallback = PKS_OI_PLUGIN_PATH . 'assets/css/bpp-poster-display-fallback.css';
		if ( is_readable( $fallback ) ) {
			$raw = file_get_contents( $fallback );
			if ( is_string( $raw ) && '' !== $raw ) {
				return $raw;
			}
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $state
	 */
	private function resolve_fonts_css( array $project, array $state ): string {
		$context = [
			'product_id'  => (int) ( $project['product_id'] ?? 0 ),
			'template_id' => (string) ( $project['template_id'] ?? '' ),
			'locale'      => (string) ( $project['locale'] ?? 'da_DK' ),
			'mode'        => 'public',
			'is_public'   => true,
			'state'       => $state,
		];

		/**
		 * @var string $css
		 */
		$css = apply_filters( PublishedPosterManifest::FILTER_FONTS, '', $context );
		if ( is_string( $css ) && '' !== trim( $css ) ) {
			return $css;
		}

		if ( function_exists( 'BPP_fonts_css' ) ) {
			$raw = BPP_fonts_css();
			if ( is_string( $raw ) && '' !== trim( $raw ) ) {
				return $raw;
			}
		}

		return '';
	}

	private function write_project_file( string $storage_uuid, string $relative_path, string $contents ): string {
		return $this->storage->write_published_asset( $storage_uuid, $relative_path, $contents );
	}
}
