<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\ProductType;

/**
 * Resolves administrator-configured envelope design for a product.
 */
final class EnvelopeDesign {

	public const SOURCE_EXPLICIT_IMAGE = 'explicit_image';
	public const SOURCE_PRESET         = 'preset';
	public const SOURCE_GALLERY        = 'gallery_fallback';

	public const FILTER = 'pks_oi/envelope_design';

	/**
	 * First WooCommerce product gallery image (index 0).
	 *
	 * Featured image is intentionally excluded — it remains the shop/catalog thumbnail.
	 */
	public const GALLERY_FALLBACK_INDEX = 0;

	/**
	 * @return array{
	 *     preset:string,
	 *     background_preset:string,
	 *     image_id:int,
	 *     image_url:string,
	 *     image_source:string,
	 *     explicit_image_id:int
	 * }
	 */
	public static function resolve_for_product( object $product ): array {
		$product_id = method_exists( $product, 'get_id' ) ? (int) $product->get_id() : 0;
		$preset     = ProductMeta::read_envelope_preset( $product );
		$background = ProductMeta::read_background_preset( $product );
		$explicit   = ProductMeta::read_envelope_image_id( $product );

		$image_id   = 0;
		$source     = self::SOURCE_PRESET;
		$image_url  = '';

		if ( $explicit > 0 ) {
			if ( AttachmentValidator::is_valid_image_attachment( $explicit ) ) {
				$image_id  = $explicit;
				$source    = self::SOURCE_EXPLICIT_IMAGE;
				$image_url = AttachmentValidator::image_url( $explicit );
			}
		} else {
			$gallery_id = self::gallery_image_id_at_index( $product, self::GALLERY_FALLBACK_INDEX );
			if ( $gallery_id > 0 && AttachmentValidator::is_valid_image_attachment( $gallery_id ) ) {
				$image_id  = $gallery_id;
				$source    = self::SOURCE_GALLERY;
				$image_url = AttachmentValidator::image_url( $gallery_id );
			}
		}

		$resolved = [
			'preset'             => $preset,
			'background_preset'  => $background,
			'image_id'           => $image_id,
			'image_url'          => $image_url,
			'image_source'       => $source,
			'explicit_image_id'  => $explicit,
		];

		/**
		 * @var array<string, mixed> $resolved
		 */
		$filtered = apply_filters( self::FILTER, $resolved, $product_id, $product );
		if ( ! is_array( $filtered ) ) {
			$filtered = $resolved;
		}

		$filtered['preset']            = sanitize_key( (string) ( $filtered['preset'] ?? '' ) );
		$filtered['background_preset']   = sanitize_key( (string) ( $filtered['background_preset'] ?? '' ) );
		$filtered['image_id']            = max( 0, (int) ( $filtered['image_id'] ?? 0 ) );
		$filtered['explicit_image_id']   = max( 0, (int) ( $filtered['explicit_image_id'] ?? $explicit ) );
		$filtered['image_source']        = sanitize_key( (string) ( $filtered['image_source'] ?? $source ) );
		$filtered['image_url']           = (string) ( $filtered['image_url'] ?? '' );

		if ( '' === $filtered['image_url'] && $filtered['image_id'] > 0 ) {
			$filtered['image_url'] = AttachmentValidator::image_url( $filtered['image_id'] );
		}

		return $filtered;
	}

	public static function gallery_image_id_at_index( object $product, int $index ): int {
		if ( ! method_exists( $product, 'get_gallery_image_ids' ) ) {
			return 0;
		}

		$gallery = $product->get_gallery_image_ids();
		if ( ! is_array( $gallery ) || ! array_key_exists( $index, $gallery ) ) {
			return 0;
		}

		return max( 0, (int) $gallery[ $index ] );
	}

	public static function image_source_label( string $source ): string {
		return match ( $source ) {
			self::SOURCE_EXPLICIT_IMAGE => __( 'Explicit envelope image', 'prikogstreg-online-invitations' ),
			self::SOURCE_GALLERY        => __( 'Product gallery fallback (first image)', 'prikogstreg-online-invitations' ),
			default                     => __( 'Preset style only', 'prikogstreg-online-invitations' ),
		};
	}
}
