<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\WooCommerce\Cart\CartPayload;
use PrikOgStreg\OnlineInvitations\WooCommerce\Cart\CartPayloadValidator;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;

/**
 * Server-side guards for purchase-to-project import.
 */
final class ProjectImportGuard {

	public static function is_already_imported( array $project ): bool {
		return ProjectEntitlement::is_project_usable( $project );
	}

	/**
	 * @param array<string, mixed> $project
	 * @param object               $order WooCommerce order.
	 * @param object               $item  WooCommerce order item.
	 */
	public static function validate_import_context( array $project, object $order, object $item ): ?string {
		$project_order_id = (int) ( $project['order_id'] ?? 0 );
		$order_id         = (int) ( method_exists( $order, 'get_id' ) ? $order->get_id() : 0 );

		if ( $project_order_id <= 0 || $order_id <= 0 || $project_order_id !== $order_id ) {
			return 'order_mismatch';
		}

		$order_item_id = (int) ( $project['order_item_id'] ?? 0 );
		$item_id       = (int) ( method_exists( $item, 'get_id' ) ? $item->get_id() : 0 );

		if ( $order_item_id <= 0 || $item_id <= 0 || $order_item_id !== $item_id ) {
			return 'order_item_mismatch';
		}

		$user_id     = (int) ( $project['user_id'] ?? 0 );
		$customer_id = (int) ( method_exists( $order, 'get_customer_id' ) ? $order->get_customer_id() : 0 );

		if ( $user_id <= 0 || $customer_id <= 0 || $user_id !== $customer_id ) {
			return 'customer_mismatch';
		}

		if ( method_exists( $item, 'get_product' ) ) {
			$product = $item->get_product();
			if ( ! is_object( $product ) || ! ProductMeta::is_online_invitation( $product ) ) {
				return 'invalid_product_type';
			}
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $state Canonical builder state.
	 */
	public static function validate_builder_pages( array $state ): ?string {
		$pages = $state['page'] ?? null;
		if ( ! is_array( $pages ) || [] === $pages ) {
			return 'missing_page_payload';
		}

		foreach ( $pages as $html ) {
			if ( ! is_string( $html ) || '' === trim( $html ) ) {
				return 'missing_page_payload';
			}
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $state
	 * @param object               $item WooCommerce order item.
	 */
	public static function validate_payload_checksum( array $state, object $item ): ?string {
		if ( ! method_exists( $item, 'get_meta' ) ) {
			return null;
		}

		$expected = (string) $item->get_meta( CartPayload::ORDER_META_CHECKSUM, true );
		if ( '' === $expected ) {
			return null;
		}

		$checksum_state = self::build_checksum_state( $state, $item );
		$validator      = new CartPayloadValidator( new BuilderService() );
		$computed       = $validator->compute_checksum( $checksum_state );

		if ( hash_equals( $expected, $computed ) ) {
			return null;
		}

		// BPP persists the authoritative payload after cart capture (filesystem file, admin edits).
		if ( null === self::validate_builder_pages( $checksum_state ) ) {
			return null;
		}

		return 'checksum_mismatch';
	}

	/**
	 * @param array<string, mixed> $state
	 * @param object               $item WooCommerce order item.
	 * @return array<string, mixed>
	 */
	public static function build_checksum_state( array $state, object $item ): array {
		$field = is_array( $state['field'] ?? null ) ? $state['field'] : [];
		$page  = is_array( $state['page'] ?? null ) ? $state['page'] : [];

		$size = (string) ( $state['size'] ?? $state['pa_bpp_size'] ?? '' );
		$format = (string) ( $state['format'] ?? $state['pa_bpp_format'] ?? '' );
		$product_id = (int) ( $state['product_id'] ?? 0 );

		if ( method_exists( $item, 'get_meta' ) ) {
			$item_size = (string) $item->get_meta( 'pa_bpp_size', true );
			if ( '' !== $item_size ) {
				$size = $item_size;
			}

			$item_format = (string) $item->get_meta( 'pa_bpp_format', true );
			if ( '' !== $item_format ) {
				$format = $item_format;
			}
		}

		if ( $product_id <= 0 && method_exists( $item, 'get_product_id' ) ) {
			$product_id = (int) $item->get_product_id();
		}

		return [
			'field'      => $field,
			'page'       => $page,
			'size'       => $size,
			'format'     => $format,
			'product_id' => $product_id,
		];
	}
}
