<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\BppAttributeDefaults;

/**
 * Supplies default template state when no customer design was captured on the order.
 */
final class ProjectTemplateFallback {

	public function __construct(
		private BuilderService $builder
	) {}

	public static function is_recoverable_import_error( string $error_code ): bool {
		return in_array(
			$error_code,
			[
				'missing_page_payload',
				'bpp_invalid_state',
				'invalid_builder_state',
				'malformed_payload',
				'checksum_mismatch',
			],
			true
		);
	}

	public static function can_fallback_for_load_error( string $error_code ): bool {
		return in_array(
			$error_code,
			[
				'bpp_invalid_state',
				'invalid_builder_state',
			],
			true
		);
	}

	/**
	 * @param array<string, mixed> $state
	 */
	public static function is_missing_customer_design( array $state ): bool {
		$pages = $state['page'] ?? null;
		if ( ! is_array( $pages ) || [] === $pages ) {
			return true;
		}

		foreach ( $pages as $html ) {
			if ( ! is_string( $html ) || '' === trim( $html ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	public function resolve_for_product( int $product_id, array $context = [] ): array {
		$state = $this->resolve_initial_state( $product_id, $context );

		if ( self::is_missing_customer_design( $state ) ) {
			$state = $this->resolve_from_bpp_product_meta( $product_id );
		}

		if ( self::is_missing_customer_design( $state ) ) {
			$state = $this->minimal_placeholder_state( $product_id );
		}

		$defaults = BppAttributeDefaults::resolve( $product_id );
		if ( ! is_wp_error( $defaults ) ) {
			if ( '' === (string) ( $state['size'] ?? '' ) ) {
				$state['size'] = $defaults['size'];
			}
			if ( '' === (string) ( $state['format'] ?? '' ) ) {
				$state['format'] = $defaults['format'];
			}
		}

		$state['product_id']  = $product_id;
		$state['template_id'] = $product_id;

		return ProjectDesignSource::mark_template_fallback( $state );
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	private function resolve_initial_state( int $product_id, array $context ): array {
		$adapter = $this->builder->get_adapter();
		if ( null === $adapter || ! method_exists( $adapter, 'create_initial_state' ) ) {
			return [];
		}

		$state = $adapter->create_initial_state( $product_id, $context );

		return is_array( $state ) ? $state : [];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function resolve_from_bpp_product_meta( int $product_id ): array {
		if ( ! class_exists( 'BPP_Product', false ) ) {
			return [];
		}

		$product = new \BPP_Product( $product_id );
		$pages   = [];

		if ( isset( $product->pages ) && is_array( $product->pages ) ) {
			foreach ( $product->pages as $page ) {
				$pages[] = is_object( $page ) && isset( $page->low_res_html )
					? (string) $page->low_res_html
					: '';
			}
		}

		$pages = array_values( array_filter( $pages, static fn( string $html ): bool => '' !== trim( $html ) ) );
		if ( [] === $pages ) {
			return [];
		}

		return [
			'schema_version' => '1',
			'field'          => [],
			'page'           => $pages,
			'size'           => (string) ( $product->default_size ?? '' ),
			'format'         => ! empty( $product->foldable ) ? 'folded' : 'flat',
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function minimal_placeholder_state( int $product_id ): array {
		return [
			'schema_version' => '1',
			'field'          => [],
			'page'           => [
				$this->design_placeholder_page_html( $product_id ),
			],
			'size'           => 'a5',
			'format'         => 'flat',
			'product_id'     => $product_id,
			'template_id'    => $product_id,
		];
	}

	public function design_placeholder_page_html( int $product_id ): string {
		$label     = __( 'Your invitation design will appear here.', 'prikogstreg-online-invitations' );
		$thumbnail = $this->bpp_page_thumbnail( $product_id, 0 );

		if ( '' !== $thumbnail ) {
			return sprintf(
				'<section class="pks-oi-design-placeholder"><img class="pks-oi-design-placeholder__image" src="%s" alt="%s" loading="lazy" decoding="async" /></section>',
				esc_attr( $this->escape_thumbnail_src( $thumbnail ) ),
				esc_attr( $label )
			);
		}

		return sprintf(
			'<section class="pks-oi-design-placeholder"><p>%s</p></section>',
			esc_html( $label )
		);
	}

	private function bpp_page_thumbnail( int $product_id, int $page_index = 0 ): string {
		if ( ! class_exists( 'BPP_Product', false ) ) {
			return '';
		}

		$product = new \BPP_Product( $product_id );
		if ( ! (bool) $product->active ) {
			return '';
		}

		$pages = array_values( (array) ( $product->pages ?? [] ) );
		$page  = $pages[ $page_index ] ?? null;
		if ( ! is_object( $page ) ) {
			return '';
		}

		return trim( (string) ( $page->thumbnail ?? '' ) );
	}

	private function escape_thumbnail_src( string $url ): string {
		if ( str_starts_with( $url, 'data:image/' ) ) {
			return $url;
		}

		return (string) esc_url( $url );
	}
}
