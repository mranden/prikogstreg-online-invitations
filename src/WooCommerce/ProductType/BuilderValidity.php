<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\ProductType;

/**
 * Validates online_invitation product readiness for purchase and publication.
 */
final class BuilderValidity {

	/**
	 * @return list<string>
	 */
	public static function validation_errors( int $product_id ): array {
		$errors = [];

		if ( $product_id <= 0 ) {
			return [ 'product_missing' ];
		}

		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;

		if ( ! ProductMeta::is_builder_optional_id( $product_id ) && ! class_exists( 'BPP_Product', false ) ) {
			$errors[] = 'bpp_plugin_unavailable';
		}

		if ( ! self::has_active_builder_template( $product_id ) && ! ProductMeta::is_builder_optional_id( $product_id ) ) {
			$errors[] = 'builder_template_missing';
		}

		if (
			self::has_active_builder_template( $product_id )
			&& ! self::has_template_pages( $product_id )
			&& ! ProductMeta::is_builder_optional_id( $product_id )
		) {
			$errors[] = 'builder_pages_missing';
		}

		if ( ! ProductMeta::is_builder_optional_id( $product_id ) ) {
			$attributes = BppAttributeDefaults::resolve( $product_id );
			if ( is_wp_error( $attributes ) ) {
				$errors[] = 'bpp_attributes_unresolved';
			}
		}

		$envelope = (string) get_post_meta( $product_id, ProductMeta::ENVELOPE_PRESET, true );
		if ( '' === $envelope || ! ProductMeta::is_envelope_preset_valid( $envelope ) ) {
			$errors[] = 'envelope_preset_missing';
		}

		$explicit_image = $product && ProductMeta::is_online_invitation( $product )
			? ProductMeta::read_envelope_image_id( $product )
			: max( 0, (int) get_post_meta( $product_id, ProductMeta::ENVELOPE_IMAGE_ID, true ) );
		if ( $explicit_image > 0 && ! AttachmentValidator::is_valid_image_attachment( $explicit_image ) ) {
			$errors[] = 'envelope_image_invalid';
		}

		$background = (string) get_post_meta( $product_id, ProductMeta::BACKGROUND_PRESET, true );
		if ( '' === $background || ! ProductMeta::is_background_preset_valid( $background ) ) {
			$errors[] = 'background_preset_missing';
		}

		if ( ! $product || ! ProductMeta::is_online_invitation( $product ) ) {
			$errors[] = 'product_type_invalid';
		} elseif ( ! self::has_valid_price( $product ) ) {
			$errors[] = 'price_missing';
		}

		return $errors;
	}

	public static function is_valid( int $product_id ): bool {
		return [] === self::validation_errors( $product_id );
	}

	public static function has_template_pages( int $product_id ): bool {
		if ( ! self::has_active_builder_template( $product_id ) ) {
			return false;
		}

		if ( ! class_exists( 'BPP_Product', false ) ) {
			return false;
		}

		$builder = new \BPP_Product( $product_id );
		$pages   = $builder->pages ?? null;

		if ( ! is_array( $pages ) && ! is_object( $pages ) ) {
			return false;
		}

		return count( (array) $pages ) > 0;
	}

	public static function has_active_builder_template( int $product_id ): bool {
		if ( class_exists( 'BPP_Product', false ) ) {
			$builder = new \BPP_Product( $product_id );

			return (bool) $builder->active;
		}

		$meta = get_post_meta( $product_id, ProductMeta::BUILDER_META_KEY, true );
		if ( ! is_object( $meta ) ) {
			return false;
		}

		return ! empty( $meta->active );
	}

	/**
	 * @return array{status:string,label:string,detail:string}
	 */
	public static function integration_status( int $product_id ): array {
		if ( self::is_valid( $product_id ) ) {
			if ( ProductMeta::is_builder_optional_id( $product_id ) && ! self::has_active_builder_template( $product_id ) ) {
				return [
					'status' => 'testing',
					'label'  => __( 'Testing without PDF Builder', 'prikogstreg-online-invitations' ),
					'detail' => __( 'PDF Builder is optional. The storefront shows a placeholder preview until a template is connected.', 'prikogstreg-online-invitations' ),
				];
			}

			return [
				'status' => 'ready',
				'label'  => __( 'Ready for purchase', 'prikogstreg-online-invitations' ),
				'detail' => __( 'PDF Builder, envelope design, background, and price are configured.', 'prikogstreg-online-invitations' ),
			];
		}

		$errors = self::validation_errors( $product_id );
		$labels = array_map( [ self::class, 'error_label' ], $errors );

		return [
			'status' => 'incomplete',
			'label'  => __( 'Configuration incomplete', 'prikogstreg-online-invitations' ),
			'detail' => implode( ' ', $labels ),
		];
	}

	public static function error_label( string $code ): string {
		return match ( $code ) {
			'bpp_plugin_unavailable'    => __( 'Activate the PDF Builder plugin before selling this product.', 'prikogstreg-online-invitations' ),
			'builder_template_missing'  => __( 'Activate and save a PDF Builder template for this product.', 'prikogstreg-online-invitations' ),
			'builder_pages_missing'     => __( 'Activate the PDF Builder template and ensure it has at least one design page.', 'prikogstreg-online-invitations' ),
			'bpp_attributes_unresolved' => __( 'Configure at least one permitted PDF Builder size and format for this design.', 'prikogstreg-online-invitations' ),
			'bpp_integration_unavailable' => __( 'The PDF Builder integration adapter is unavailable. Re-activate the PDF Builder plugin.', 'prikogstreg-online-invitations' ),
			'envelope_preset_missing'   => __( 'Select a valid envelope preset.', 'prikogstreg-online-invitations' ),
			'envelope_image_invalid'    => __( 'Select a valid image attachment for the envelope image field.', 'prikogstreg-online-invitations' ),
			'background_preset_missing' => __( 'Select a valid background preset.', 'prikogstreg-online-invitations' ),
			'price_missing'             => __( 'Set a product price before allowing purchase.', 'prikogstreg-online-invitations' ),
			'product_not_purchasable'   => __( 'Mark the product as purchasable before allowing purchase.', 'prikogstreg-online-invitations' ),
			'product_missing',
			'product_type_invalid'      => __( 'Invitation configuration is incomplete.', 'prikogstreg-online-invitations' ),
			default                     => __( 'Invitation configuration is incomplete.', 'prikogstreg-online-invitations' ),
		};
	}

	private static function has_valid_price( object $product ): bool {
		if ( ! method_exists( $product, 'get_price' ) ) {
			return false;
		}

		$price = $product->get_price();

		return '' !== $price && null !== $price;
	}
}
