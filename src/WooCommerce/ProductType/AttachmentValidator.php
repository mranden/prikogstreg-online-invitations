<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\ProductType;

/**
 * Validates WordPress media attachments for product configuration.
 */
final class AttachmentValidator {

	public static function is_valid_image_attachment( int $attachment_id ): bool {
		if ( $attachment_id <= 0 ) {
			return false;
		}

		if ( ! function_exists( 'get_post_type' ) || 'attachment' !== get_post_type( $attachment_id ) ) {
			return false;
		}

		if ( function_exists( 'wp_attachment_is_image' ) ) {
			return (bool) wp_attachment_is_image( $attachment_id );
		}

		$mime = (string) get_post_mime_type( $attachment_id );

		return str_starts_with( $mime, 'image/' );
	}

	public static function image_url( int $attachment_id, string $size = 'medium_large' ): string {
		if ( ! self::is_valid_image_attachment( $attachment_id ) ) {
			return '';
		}

		if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
			return '';
		}

		return (string) wp_get_attachment_image_url( $attachment_id, $size );
	}
}
