<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Rsvp;

/**
 * Sanitizes guest-facing RSVP text fields.
 */
final class RsvpSanitizer {

	public static function comment( string $value ): string {
		$value = wp_strip_all_tags( $value );

		return sanitize_textarea_field( $value );
	}

	public static function dietary_notes( string $value ): string {
		$value = wp_strip_all_tags( $value );

		return sanitize_textarea_field( $value );
	}

	public static function display_name( string $value ): string {
		return sanitize_text_field( trim( $value ) );
	}

	public static function email( string $value ): ?string {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}

		$email = sanitize_email( $value );

		return '' !== $email ? $email : null;
	}
}
