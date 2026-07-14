<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Photo;

/**
 * Owner-visible photo code for My Account (plaintext — not used for verification).
 */
final class PhotoAccessCodeDisplayStore {

	public const META_KEY = '_pks_oi_photo_access_code_plain';

	public static function remember( int $project_id, string $code ): void {
		$code = trim( $code );
		if ( $project_id <= 0 || '' === $code ) {
			return;
		}

		update_post_meta( $project_id, self::META_KEY, $code );
	}

	public static function forget( int $project_id ): void {
		if ( $project_id <= 0 ) {
			return;
		}

		delete_post_meta( $project_id, self::META_KEY );
	}

	public static function read( int $project_id ): string {
		if ( $project_id <= 0 ) {
			return '';
		}

		$stored = get_post_meta( $project_id, self::META_KEY, true );

		return is_string( $stored ) ? trim( $stored ) : '';
	}
}
