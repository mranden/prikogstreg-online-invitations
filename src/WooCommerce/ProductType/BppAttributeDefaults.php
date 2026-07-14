<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\ProductType;

/**
 * Resolves permitted BPP size/format defaults for simple online_invitation products.
 */
final class BppAttributeDefaults {

	public const FILTER = 'pks_oi/bpp_attribute_defaults';

	/**
	 * @return array{size:string,format:string}|\WP_Error
	 */
	public static function resolve( int $product_id ): array|\WP_Error {
		if ( $product_id <= 0 ) {
			return new \WP_Error( 'bpp_invalid_product', 'Invalid product ID.' );
		}

		if ( ! class_exists( 'BPP_Product', false ) ) {
			return new \WP_Error( 'bpp_unavailable', 'PDF Builder is not available.' );
		}

		$builder = new \BPP_Product( $product_id );
		if ( ! (bool) $builder->active ) {
			return new \WP_Error( 'bpp_inactive', 'PDF Builder template is not active.' );
		}

		return self::resolve_from_model(
			[
				'type'            => (string) $builder->type,
				'foldable'        => ! empty( $builder->foldable ),
				'default_size'    => (string) $builder->default_size,
				'available_sizes' => self::extract_available_sizes( $builder ),
			],
			$product_id
		);
	}

	/**
	 * @param array{type:string,foldable:bool,default_size:string,available_sizes:array<string, array<int, array<string, mixed>>>} $model
	 * @return array{size:string,format:string}|\WP_Error
	 */
	public static function resolve_from_model( array $model, int $product_id = 0 ): array|\WP_Error {
		$type = (string) ( $model['type'] ?? '' );
		if ( '' === $type ) {
			return new \WP_Error( 'bpp_type_missing', 'PDF Builder product type is missing.' );
		}

		$sizes = self::permitted_size_slugs( $model, $type );
		if ( [] === $sizes ) {
			return new \WP_Error( 'bpp_size_unavailable', 'No permitted PDF Builder size is configured for this product.' );
		}

		$formats = self::permitted_format_slugs( $model );
		if ( [] === $formats ) {
			return new \WP_Error( 'bpp_format_unavailable', 'No permitted PDF Builder format is configured for this product.' );
		}

		$default_size = sanitize_title( (string) ( $model['default_size'] ?? '' ) );
		if ( ! in_array( $default_size, $sizes, true ) ) {
			$default_size = $sizes[0];
		}

		$defaults = [
			'size'   => $default_size,
			'format' => $formats[0],
		];

		/**
		 * @var array{size:string,format:string} $defaults
		 */
		$defaults = apply_filters( self::FILTER, $defaults, $product_id, $model );

		if ( ! is_array( $defaults ) || ! isset( $defaults['size'], $defaults['format'] ) ) {
			return new \WP_Error( 'bpp_defaults_invalid', 'PDF Builder defaults filter returned an invalid value.' );
		}

		$size   = sanitize_title( (string) $defaults['size'] );
		$format = sanitize_title( (string) $defaults['format'] );

		if ( ! in_array( $size, $sizes, true ) ) {
			return new \WP_Error( 'bpp_size_invalid', 'Resolved PDF Builder size is not permitted for this product.' );
		}

		if ( ! in_array( $format, $formats, true ) ) {
			return new \WP_Error( 'bpp_format_invalid', 'Resolved PDF Builder format is not permitted for this product.' );
		}

		return [
			'size'   => $size,
			'format' => $format,
		];
	}

	/**
	 * @return array{size:string,format:string}|\WP_Error
	 */
	public static function normalize_posted_attributes( int $product_id, string $posted_size, string $posted_format ): array|\WP_Error {
		$resolved = self::resolve( $product_id );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$size   = sanitize_title( $posted_size );
		$format = sanitize_title( $posted_format );

		if ( '' === $size ) {
			$size = $resolved['size'];
		}

		if ( '' === $format ) {
			$format = $resolved['format'];
		}

		if ( ! class_exists( 'BPP_Product', false ) ) {
			return new \WP_Error( 'bpp_unavailable', 'PDF Builder is not available.' );
		}

		$builder = new \BPP_Product( $product_id );
		$model   = [
			'type'            => (string) $builder->type,
			'foldable'        => ! empty( $builder->foldable ),
			'default_size'    => (string) $builder->default_size,
			'available_sizes' => self::extract_available_sizes( $builder ),
		];

		$permitted_sizes   = self::permitted_size_slugs( $model, (string) $builder->type );
		$permitted_formats = self::permitted_format_slugs( $model );

		if ( ! in_array( $size, $permitted_sizes, true ) ) {
			return new \WP_Error( 'bpp_size_not_permitted', 'Selected PDF Builder size is not available for this design.' );
		}

		if ( ! in_array( $format, $permitted_formats, true ) ) {
			return new \WP_Error( 'bpp_format_not_permitted', 'Selected PDF Builder format is not available for this design.' );
		}

		return [
			'size'   => $size,
			'format' => $format,
		];
	}

	public static function customer_error_message( \WP_Error $error ): string {
		return match ( $error->get_error_code() ) {
			'bpp_size_unavailable',
			'bpp_size_invalid',
			'bpp_size_not_permitted' => __( 'This invitation design does not have a valid print size configured. Please contact the shop for help.', 'prikogstreg-online-invitations' ),
			'bpp_format_unavailable',
			'bpp_format_invalid',
			'bpp_format_not_permitted' => __( 'This invitation design does not have a valid print format configured. Please contact the shop for help.', 'prikogstreg-online-invitations' ),
			'bpp_unavailable',
			'bpp_inactive' => __( 'The PDF Builder is not available for this product.', 'prikogstreg-online-invitations' ),
			default => __( 'This invitation cannot be added to the cart until the PDF Builder configuration is complete.', 'prikogstreg-online-invitations' ),
		};
	}

	/**
	 * @param object $builder
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	private static function extract_available_sizes( object $builder ): array {
		$sizes = $builder->available_sizes ?? [];

		return is_array( $sizes ) ? $sizes : [];
	}

	/**
	 * @param array{type?:string,foldable?:bool,available_sizes?:array<string, array<int, array<string, mixed>>>} $model
	 * @return list<string>
	 */
	private static function permitted_size_slugs( array $model, string $type ): array {
		$entries = $model['available_sizes'][ $type ] ?? [];
		if ( ! is_array( $entries ) ) {
			return [];
		}

		$slugs = [];
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['available'] ) ) {
				continue;
			}

			$slug = sanitize_title( (string) ( $entry['attribute_slug'] ?? '' ) );
			if ( '' !== $slug ) {
				$slugs[] = $slug;
			}
		}

		return array_values( array_unique( $slugs ) );
	}

	/**
	 * @param array{foldable?:bool,type?:string} $model
	 * @return list<string>
	 */
	private static function permitted_format_slugs( array $model ): array {
		$formats = [ 'flat' ];

		if ( ! empty( $model['foldable'] ) && 'invitation' === (string) ( $model['type'] ?? '' ) ) {
			$formats[] = 'folded';
		}

		return $formats;
	}
}
