<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageException;
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
	 * Enqueues poster display CSS/fonts for authenticated My Account design/preview sections.
	 *
	 * @param array<string, mixed> $project
	 */
	public function enqueue_account_preview( array $project ): void {
		$parent_handle = 'pks-oi-account';

		wp_enqueue_style(
			'pks-oi-bpp-poster-fallback',
			PKS_OI_PLUGIN_URL . 'assets/css/bpp-poster-display-fallback.css',
			[ $parent_handle ],
			PKS_OI_VERSION
		);

		$storage_uuid = (string) ( $project['storage_uuid'] ?? '' );
		if ( '' === $storage_uuid ) {
			$this->enqueue_runtime_fallback( $project, $parent_handle );

			return;
		}

		$manifest       = $this->storage->try_read_poster_manifest( $storage_uuid );
		$loaded_display = false;
		$loaded_fonts   = false;

		if ( null !== $manifest ) {
			if ( null !== $manifest->display_css_path && '' !== $manifest->display_css_path ) {
				try {
					$css = $this->storage->read_published_asset( $storage_uuid, $manifest->display_css_path );
					if ( '' !== trim( $css ) ) {
						wp_register_style( 'pks-oi-account-poster-display', false, [ $parent_handle, 'pks-oi-bpp-poster-fallback' ], PKS_OI_VERSION );
						wp_enqueue_style( 'pks-oi-account-poster-display' );
						wp_add_inline_style( 'pks-oi-account-poster-display', $css );
						$loaded_display = true;
					}
				} catch ( StorageException $exception ) {
					$loaded_display = false;
				}
			}

			if ( null !== $manifest->fonts_css_path && '' !== $manifest->fonts_css_path ) {
				try {
					$fonts = $this->storage->read_published_asset( $storage_uuid, $manifest->fonts_css_path );
					if ( '' !== trim( $fonts ) ) {
						wp_register_style( 'pks-oi-account-poster-fonts', false, [ $parent_handle ], PKS_OI_VERSION );
						wp_enqueue_style( 'pks-oi-account-poster-fonts' );
						wp_add_inline_style( 'pks-oi-account-poster-fonts', $fonts );
						$loaded_fonts = true;
					}
				} catch ( StorageException $exception ) {
					$loaded_fonts = false;
				}
			}
		}

		if ( ! $loaded_display ) {
			$this->enqueue_runtime_fallback( $project, $parent_handle );
		} elseif ( ! $loaded_fonts ) {
			$this->enqueue_runtime_fonts( $parent_handle );
		}
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function enqueue_runtime_fallback( array $project, string $parent_handle = 'pks-oi-public' ): void {
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
			wp_enqueue_style( 'bpp-public-css', $url, [ $parent_handle ], null );
		}

		$this->enqueue_runtime_fonts( $parent_handle );
	}

	private function enqueue_runtime_fonts( string $parent_handle = 'pks-oi-public' ): void {
		if ( ! function_exists( 'BPP_fonts_css' ) ) {
			return;
		}

		$fonts = BPP_fonts_css();
		if ( ! is_string( $fonts ) || '' === trim( $fonts ) ) {
			return;
		}

		wp_register_style( 'pks-oi-poster-fonts-runtime', false, [ $parent_handle ], PKS_OI_VERSION );
		wp_enqueue_style( 'pks-oi-poster-fonts-runtime' );
		wp_add_inline_style( 'pks-oi-poster-fonts-runtime', $fonts );
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

		return PosterDimensions::resolve(
			$this->resolve_project_size( $project ),
			$this->resolve_project_format( $project ),
			$sample_html
		);
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function resolve_project_size( array $project ): string {
		$from_state = $this->read_state_field( $project, 'size' );
		if ( '' !== $from_state ) {
			return $from_state;
		}

		return sanitize_title( (string) ( $project['size'] ?? 'a5' ) );
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function resolve_project_format( array $project ): string {
		$from_state = $this->read_state_field( $project, 'format' );
		if ( '' !== $from_state ) {
			return $from_state;
		}

		return sanitize_title( (string) ( $project['format'] ?? 'flat' ) );
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function read_state_field( array $project, string $field ): string {
		$storage_uuid = (string) ( $project['storage_uuid'] ?? '' );
		if ( '' === $storage_uuid ) {
			return '';
		}

		try {
			$json    = $this->storage->read_current_state( $storage_uuid, false );
			$decoded = json_decode( $json, true );
		} catch ( StorageException $exception ) {
			return '';
		}

		if ( ! is_array( $decoded ) ) {
			return '';
		}

		return sanitize_title( (string) ( $decoded[ $field ] ?? '' ) );
	}
}
