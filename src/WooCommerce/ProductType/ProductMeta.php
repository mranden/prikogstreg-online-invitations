<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\ProductType;

/**
 * Product meta keys and preset allowlists for online_invitation products.
 */
final class ProductMeta {

	public const TYPE = 'online_invitation';

	public const ENVELOPE_PRESET       = '_pks_oi_envelope_preset';
	public const ENVELOPE_PREVIEW_REF  = '_pks_oi_envelope_preview_ref';
	public const BACKGROUND_PRESET     = '_pks_oi_background_preset';
	public const DEFAULT_LOCALE        = '_pks_oi_default_locale';
	public const REMINDER_OFFSET_DAYS  = '_pks_oi_reminder_offset_days';
	public const GUEST_PHOTOS_DEFAULT  = '_pks_oi_guest_photos_default';
	public const WISHLIST_DEFAULT      = '_pks_oi_wishlist_default';
	public const BUILDER_OPTIONAL        = '_pks_oi_builder_optional';

	public const BUILDER_META_KEY = '_bpp_product';

	public const DEFAULT_LOCALE_VALUE       = 'da_DK';
	public const DEFAULT_REMINDER_OFFSET    = 5;
	public const DEFAULT_GUEST_PHOTOS       = 'yes';
	public const DEFAULT_WISHLIST           = 'yes';

	/**
	 * @return array<string, string>
	 */
	public static function envelope_presets(): array {
		return [
			'classic' => __( 'Classic envelope', 'prikogstreg-online-invitations' ),
			'modern'  => __( 'Modern envelope', 'prikogstreg-online-invitations' ),
			'minimal' => __( 'Minimal envelope', 'prikogstreg-online-invitations' ),
		];
	}

	/**
	 * @return array<string, string>
	 */
	public static function background_presets(): array {
		return [
			'neutral'   => __( 'Neutral background', 'prikogstreg-online-invitations' ),
			'floral'    => __( 'Floral background', 'prikogstreg-online-invitations' ),
			'geometric' => __( 'Geometric background', 'prikogstreg-online-invitations' ),
		];
	}

	public static function is_envelope_preset_valid( string $preset ): bool {
		return array_key_exists( $preset, self::envelope_presets() );
	}

	public static function is_background_preset_valid( string $preset ): bool {
		return array_key_exists( $preset, self::background_presets() );
	}

	/**
	 * @param object $product WooCommerce product object.
	 */
	public static function is_online_invitation( object $product ): bool {
		return method_exists( $product, 'is_type' ) && $product->is_type( self::TYPE );
	}

	public static function is_builder_optional_id( int $product_id ): bool {
		if ( $product_id <= 0 ) {
			return false;
		}

		$product = wc_get_product( $product_id );

		return null !== $product && self::is_builder_optional( $product );
	}

	/**
	 * @param object $product WooCommerce product object.
	 */
	public static function is_builder_optional( object $product ): bool {
		if ( ! self::is_online_invitation( $product ) ) {
			return false;
		}

		return wc_string_to_bool( (string) $product->get_meta( self::BUILDER_OPTIONAL, true ) );
	}

	/**
	 * Apply first-run defaults so price/inventory panels and purchase checks have baseline meta.
	 *
	 * @param object $product WooCommerce product object.
	 */
	public static function ensure_defaults( object $product ): void {
		if ( ! self::is_online_invitation( $product ) ) {
			return;
		}

		if ( '' === (string) $product->get_meta( self::ENVELOPE_PRESET, true ) ) {
			$product->update_meta_data( self::ENVELOPE_PRESET, 'classic' );
		}

		if ( '' === (string) $product->get_meta( self::BACKGROUND_PRESET, true ) ) {
			$product->update_meta_data( self::BACKGROUND_PRESET, 'neutral' );
		}

		if ( '' === (string) $product->get_meta( self::DEFAULT_LOCALE, true ) ) {
			$product->update_meta_data( self::DEFAULT_LOCALE, self::DEFAULT_LOCALE_VALUE );
		}

		if ( method_exists( $product, 'set_virtual' ) ) {
			$product->set_virtual( true );
		}

		if ( method_exists( $product, 'set_sold_individually' ) ) {
			$product->set_sold_individually( true );
		}
	}

	/**
	 * @param object $product WooCommerce product object.
	 */
	public static function read_envelope_preset( object $product ): string {
		return (string) $product->get_meta( self::ENVELOPE_PRESET, true );
	}

	/**
	 * @param object $product WooCommerce product object.
	 */
	public static function read_background_preset( object $product ): string {
		return (string) $product->get_meta( self::BACKGROUND_PRESET, true );
	}

	/**
	 * @param object $product WooCommerce product object.
	 */
	public static function read_default_locale( object $product ): string {
		$locale = (string) $product->get_meta( self::DEFAULT_LOCALE, true );

		return '' !== $locale ? $locale : self::DEFAULT_LOCALE_VALUE;
	}

	/**
	 * @param object $product WooCommerce product object.
	 */
	public static function read_reminder_offset_days( object $product ): int {
		$value = $product->get_meta( self::REMINDER_OFFSET_DAYS, true );

		if ( '' === $value || null === $value ) {
			return self::DEFAULT_REMINDER_OFFSET;
		}

		return max( 1, min( 30, (int) $value ) );
	}

	/**
	 * @param object $product WooCommerce product object.
	 */
	public static function read_guest_photos_default( object $product ): bool {
		$value = $product->get_meta( self::GUEST_PHOTOS_DEFAULT, true );

		if ( '' === $value || null === $value ) {
			return true;
		}

		return wc_string_to_bool( (string) $value );
	}

	/**
	 * @param object $product WooCommerce product object.
	 */
	public static function read_wishlist_default( object $product ): bool {
		$value = $product->get_meta( self::WISHLIST_DEFAULT, true );

		if ( '' === $value || null === $value ) {
			return true;
		}

		return wc_string_to_bool( (string) $value );
	}

	/**
	 * @param object               $product WooCommerce product object.
	 * @param array<string, mixed> $data    Posted admin form values.
	 */
	public static function save_admin_fields( object $product, array $data ): void {
		$envelope = sanitize_key( (string) ( $data[ self::ENVELOPE_PRESET ] ?? '' ) );
		if ( self::is_envelope_preset_valid( $envelope ) ) {
			$product->update_meta_data( self::ENVELOPE_PRESET, $envelope );
		} else {
			$product->update_meta_data( self::ENVELOPE_PRESET, '' );
		}

		$preview_ref = sanitize_text_field( (string) ( $data[ self::ENVELOPE_PREVIEW_REF ] ?? '' ) );
		$product->update_meta_data( self::ENVELOPE_PREVIEW_REF, $preview_ref );

		$background = sanitize_key( (string) ( $data[ self::BACKGROUND_PRESET ] ?? '' ) );
		if ( self::is_background_preset_valid( $background ) ) {
			$product->update_meta_data( self::BACKGROUND_PRESET, $background );
		} else {
			$product->update_meta_data( self::BACKGROUND_PRESET, '' );
		}

		$locale = sanitize_text_field( (string) ( $data[ self::DEFAULT_LOCALE ] ?? self::DEFAULT_LOCALE_VALUE ) );
		$product->update_meta_data( self::DEFAULT_LOCALE, '' !== $locale ? $locale : self::DEFAULT_LOCALE_VALUE );

		$reminder = max( 1, min( 30, (int) ( $data[ self::REMINDER_OFFSET_DAYS ] ?? self::DEFAULT_REMINDER_OFFSET ) ) );
		$product->update_meta_data( self::REMINDER_OFFSET_DAYS, $reminder );

		$guest_photos = isset( $data[ self::GUEST_PHOTOS_DEFAULT ] ) ? 'yes' : 'no';
		$product->update_meta_data( self::GUEST_PHOTOS_DEFAULT, $guest_photos );

		$wishlist = isset( $data[ self::WISHLIST_DEFAULT ] ) ? 'yes' : 'no';
		$product->update_meta_data( self::WISHLIST_DEFAULT, $wishlist );

		$builder_optional = isset( $data[ self::BUILDER_OPTIONAL ] ) ? 'yes' : 'no';
		$product->update_meta_data( self::BUILDER_OPTIONAL, $builder_optional );
	}
}
