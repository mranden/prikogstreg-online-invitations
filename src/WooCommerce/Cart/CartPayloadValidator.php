<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\Cart;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;

/**
 * Validates builder cart payloads for online_invitation products.
 */
final class CartPayloadValidator {

	public function __construct(
		private BuilderService $builder
	) {}

	/**
	 * @return list<string>
	 */
	public function validate_posted_payload( int $product_id ): array {
		$state = $this->build_state_from_request( $product_id );
		$errors = $this->structural_errors( $state );

		if ( [] !== $errors ) {
			return $errors;
		}

		return $this->adapter_errors( $state, $product_id );
	}

	/**
	 * @param array<string, mixed> $cart_item
	 * @return list<string>
	 */
	public function validate_cart_item( array $cart_item ): array {
		if ( ! CartPayload::is_invitation_line( $cart_item ) ) {
			$product = $cart_item['data'] ?? null;
			if ( ! $product || ! ProductMeta::is_online_invitation( $product ) ) {
				return [];
			}
		}

		$state = [
			'field'      => $cart_item['field'] ?? [],
			'page'       => $cart_item['page'] ?? [],
			'size'       => $cart_item['pa_bpp_size'] ?? '',
			'format'     => $cart_item['pa_bpp_format'] ?? '',
			'product_id' => (int) ( $cart_item['product_id'] ?? 0 ),
		];

		$errors = $this->structural_errors( $state );

		if ( [] !== $errors ) {
			return $errors;
		}

		return $this->adapter_errors( $state, (int) ( $cart_item['product_id'] ?? 0 ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function build_state_from_request( int $product_id ): array {
		$field = [];
		if ( isset( $_POST['field'] ) && is_array( $_POST['field'] ) ) {
			foreach ( $_POST['field'] as $uuid => $data ) {
				$field[ (string) $uuid ] = is_array( $data ) ? wp_unslash( $data ) : [];
			}
		}

		$page = [];
		if ( isset( $_POST['page'] ) && is_array( $_POST['page'] ) ) {
			$page = array_map( 'strval', wp_unslash( $_POST['page'] ) );
		}

		return [
			'field'       => $field,
			'page'        => $page,
			'size'        => sanitize_text_field( wp_unslash( (string) ( $_POST['attribute_pa_bpp_size'] ?? '' ) ) ),
			'format'      => sanitize_text_field( wp_unslash( (string) ( $_POST['attribute_pa_bpp_format'] ?? '' ) ) ),
			'product_id'  => $product_id,
			'template_id' => $product_id,
		];
	}

	/**
	 * Lightweight checksum without storing large blobs in extra keys.
	 *
	 * @param array<string, mixed> $state
	 */
	public function compute_checksum( array $state ): string {
		$field = is_array( $state['field'] ?? null ) ? $state['field'] : [];
		$page  = is_array( $state['page'] ?? null ) ? $state['page'] : [];

		$manifest = [
			'field_keys'   => array_keys( $field ),
			'page_count'   => count( $page ),
			'page_lengths' => array_map( 'strlen', $page ),
			'size'         => (string) ( $state['size'] ?? '' ),
			'format'       => (string) ( $state['format'] ?? '' ),
			'product_id'   => (int) ( $state['product_id'] ?? 0 ),
		];

		$json = json_encode( $manifest, JSON_UNESCAPED_SLASHES );

		return hash( 'sha256', is_string( $json ) ? $json : '' );
	}

	/**
	 * @param array<string, mixed> $state
	 * @return list<string>
	 */
	private function structural_errors( array $state ): array {
		$errors = [];

		$field = $state['field'] ?? [];
		$page  = $state['page'] ?? [];

		if ( ! is_array( $field ) || [] === $field ) {
			$errors[] = 'missing_field_payload';
		}

		if ( ! is_array( $page ) || [] === $page ) {
			$errors[] = 'missing_page_payload';
		}

		if ( '' === (string) ( $state['size'] ?? '' ) ) {
			$errors[] = 'missing_size';
		}

		if ( '' === (string) ( $state['format'] ?? '' ) ) {
			$errors[] = 'missing_format';
		}

		return $errors;
	}

	/**
	 * @param array<string, mixed> $state
	 * @return list<string>
	 */
	private function adapter_errors( array $state, int $product_id ): array {
		$adapter = $this->builder->get_adapter();
		if ( null === $adapter || ! method_exists( $adapter, 'validate_state' ) ) {
			return [];
		}

		$result = $adapter->validate_state(
			$state,
			[
				'source'     => 'online_invitation',
				'mode'       => 'cart',
				'product_id' => $product_id,
			]
		);

		if ( is_wp_error( $result ) ) {
			return [ $result->get_error_code() ?: 'invalid_builder_state' ];
		}

		return [];
	}
}
