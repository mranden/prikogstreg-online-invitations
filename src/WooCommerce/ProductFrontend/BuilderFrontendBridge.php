<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\ProductFrontend;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\BppAttributeDefaults;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\BuilderValidity;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;

/**
 * Single storefront gateway for PDF Builder composition on online_invitation products.
 *
 * Other Online Invitations code must not call BPP_* classes or woocommerce_bpp_options directly.
 */
final class BuilderFrontendBridge {

	public const FIELD_FORM_ACTION = 'woocommerce_bpp_options';

	/** @var array<int, true> */
	private static array $field_form_rendered_for_product = [];

	public function __construct(
		private BuilderService $builder_service
	) {}

	public function is_builder_plugin_available(): bool {
		return class_exists( 'BPP_Product', false );
	}

	public function is_integration_available(): bool {
		return $this->builder_service->is_available();
	}

	public function is_integration_registered(): bool {
		return $this->builder_service->is_integration_registered();
	}

	public function is_customizable_product( $product = null ): bool {
		if ( null === $product ) {
			$product = wc_get_product();
		}

		if ( ! $product || ! ProductMeta::is_online_invitation( $product ) ) {
			return false;
		}

		if ( ProductMeta::is_builder_optional( $product ) ) {
			return false;
		}

		$product_id = (int) $product->get_id();

		return (bool) apply_filters( 'bpp/is_product_customizable', false, $product_id );
	}

	public function has_active_template( int $product_id ): bool {
		return BuilderValidity::has_active_builder_template( $product_id );
	}

	public function has_template_pages( int $product_id ): bool {
		return BuilderValidity::has_template_pages( $product_id );
	}

	/**
	 * @return array{size:string,format:string}|\WP_Error
	 */
	public function resolve_attribute_defaults( int $product_id ): array|\WP_Error {
		return BppAttributeDefaults::resolve( $product_id );
	}

	/**
	 * @return array{size:string,format:string}|\WP_Error
	 */
	public function normalize_posted_attributes( int $product_id, string $posted_size, string $posted_format ): array|\WP_Error {
		return BppAttributeDefaults::normalize_posted_attributes( $product_id, $posted_size, $posted_format );
	}

	/**
	 * BPP enqueues product-page assets when is_product() and the loaded product template is active.
	 * OI must not duplicate bpp-public-js, cropper, or localization objects.
	 */
	public function expects_native_builder_assets_on_product_page( $product = null ): bool {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return false;
		}

		if ( null === $product ) {
			$product = wc_get_product();
		}

		if ( ! $product || ! ProductMeta::is_online_invitation( $product ) ) {
			return false;
		}

		return $this->has_active_template( (int) $product->get_id() );
	}

	/**
	 * Canvas HTML is rendered by the active theme via BPP_PDF_Plugin::content_single_product().
	 * The add-to-cart template must never output #customizer-area or #working_div.
	 */
	public function expects_theme_canvas(): bool {
		return $this->is_builder_plugin_available();
	}

	/**
	 * @return list<string>
	 */
	public function builder_readiness_errors( int $product_id ): array {
		if ( $product_id <= 0 || ProductMeta::is_builder_optional_id( $product_id ) ) {
			return [];
		}

		$errors = [];

		if ( ! $this->is_builder_plugin_available() ) {
			$errors[] = 'bpp_plugin_unavailable';
		}

		if ( ! $this->has_active_template( $product_id ) ) {
			$errors[] = 'builder_template_missing';
		} elseif ( ! $this->has_template_pages( $product_id ) ) {
			$errors[] = 'builder_pages_missing';
		}

		if ( $this->is_builder_plugin_available() && $this->has_active_template( $product_id ) ) {
			$defaults = $this->resolve_attribute_defaults( $product_id );
			if ( is_wp_error( $defaults ) ) {
				$errors[] = 'bpp_attributes_unresolved';
			}
		}

		return array_values( array_unique( $errors ) );
	}

	public function customer_readiness_message( string $error_code ): string {
		return match ( $error_code ) {
			'bpp_plugin_unavailable',
			'bpp_integration_unavailable' => __( 'This invitation is temporarily unavailable. Please try again later or contact the shop.', 'prikogstreg-online-invitations' ),
			'builder_template_missing',
			'builder_pages_missing' => __( 'This invitation is not available for purchase yet.', 'prikogstreg-online-invitations' ),
			'bpp_attributes_unresolved' => __( 'This invitation design is missing a valid size or format configuration.', 'prikogstreg-online-invitations' ),
			default => __( 'This invitation cannot be purchased right now.', 'prikogstreg-online-invitations' ),
		};
	}

	public function is_field_form_action_registered(): bool {
		return has_action( self::FIELD_FORM_ACTION ) > 0;
	}

	public function should_render_builder_fields( $product = null ): bool {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return false;
		}

		if ( ! $this->is_customizable_product( $product ) ) {
			return false;
		}

		if ( null === $product ) {
			$product = wc_get_product();
		}

		if ( ! $product ) {
			return false;
		}

		$product_id = (int) $product->get_id();
		if ( [] !== $this->builder_readiness_errors( $product_id ) ) {
			return false;
		}

		if ( ! $this->is_field_form_action_registered() ) {
			return false;
		}

		return true;
	}

	public function uses_bpp_purchase_button( $product = null ): bool {
		return $this->should_render_builder_fields( $product );
	}

	public function render_builder_fields( $product = null ): void {
		if ( ! $this->should_render_builder_fields( $product ) ) {
			return;
		}

		if ( null === $product ) {
			$product = wc_get_product();
		}

		$product_id = $product && method_exists( $product, 'get_id' ) ? (int) $product->get_id() : 0;
		if ( $product_id <= 0 || $this->field_form_already_rendered( $product_id ) ) {
			return;
		}

		$defaults = $this->resolve_attribute_defaults( $product_id );
		if ( is_wp_error( $defaults ) ) {
			return;
		}

		if ( $this->should_emit_hidden_attribute_inputs( $product ) ) {
			$this->render_hidden_attribute_inputs( $defaults );
		}

		$this->render_field_form_via_action();
		$this->mark_field_form_rendered( $product_id );
	}

	private function field_form_already_rendered( int $product_id ): bool {
		return isset( self::$field_form_rendered_for_product[ $product_id ] )
			|| did_action( self::FIELD_FORM_ACTION ) > 0;
	}

	private function mark_field_form_rendered( int $product_id ): void {
		self::$field_form_rendered_for_product[ $product_id ] = true;
	}

	private function should_emit_hidden_attribute_inputs( $product ): bool {
		if ( ! $product || ! method_exists( $product, 'is_type' ) ) {
			return true;
		}

		return ! $product->is_type( 'variable' );
	}

	/**
	 * @param array{size:string,format:string} $defaults
	 */
	private function render_hidden_attribute_inputs( array $defaults ): void {
		// BPP public.js reads #pa_bpp_size during cart preview capture (not attribute_pa_bpp_size).
		printf(
			'<input type="hidden" id="pa_bpp_size" name="attribute_pa_bpp_size" value="%s" />',
			esc_attr( $defaults['size'] )
		);
		printf(
			'<input type="hidden" id="pa_bpp_format" name="attribute_pa_bpp_format" value="%s" />',
			esc_attr( $defaults['format'] )
		);
	}

	private function render_field_form_via_action(): void {
		/**
		 * Compatibility path: BPP_Hooks::customizer_view_right() → BPP_Product::render_product_customizer_form().
		 */
		do_action( self::FIELD_FORM_ACTION );
	}

	/**
	 * Read a page thumbnail URL from the BPP product template (_bpp_product meta).
	 *
	 * This is the storefront-safe equivalent of admin #page-thumbnail-{n} values.
	 *
	 * @return string Image URL or base64 data URL; empty when unavailable.
	 */
	public function get_page_thumbnail( int $product_id, int $page_index = 0 ): string {
		$map = $this->get_page_thumbnail_map( $product_id );

		return (string) ( $map[ $page_index ] ?? '' );
	}

	/**
	 * Mirrors BPP_PDF_Plugin::content_single_product() active page selection.
	 */
	public function get_storefront_active_page_index( int $product_id ): int {
		if ( ! $this->is_builder_plugin_available() || ! $this->has_active_template( $product_id ) ) {
			return 0;
		}

		$builder = new \BPP_Product( $product_id );
		if ( ! $builder->active ) {
			return 0;
		}

		$pages = array_values( (array) ( $builder->pages ?? [] ) );

		return 4 === count( $pages ) ? 1 : 0;
	}

	/**
	 * @return array<int, string> Page index => thumbnail URL.
	 */
	public function get_page_thumbnail_map( int $product_id ): array {
		if ( ! $this->is_builder_plugin_available() || ! $this->has_active_template( $product_id ) ) {
			return [];
		}

		$builder = new \BPP_Product( $product_id );
		if ( ! $builder->active ) {
			return [];
		}

		$pages = array_values( (array) ( $builder->pages ?? [] ) );
		$map   = [];

		foreach ( $pages as $index => $page ) {
			$thumbnail = is_object( $page ) ? (string) ( $page->thumbnail ?? '' ) : '';
			if ( '' !== $thumbnail ) {
				$map[ (int) $index ] = $thumbnail;
			}
		}

		return $map;
	}

	/**
	 * Test helper to reset render guard between assertions.
	 */
	public static function reset_render_state_for_tests(): void {
		self::$field_form_rendered_for_product = [];
	}
}
