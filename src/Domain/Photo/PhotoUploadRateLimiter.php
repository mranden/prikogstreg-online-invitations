<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Photo;

use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoModerationStatus;

/**
 * Per-token upload intent rate limiting.
 */
final class PhotoUploadRateLimiter {

	public function allow( string $token_hash ): bool {
		$key = 'pks_oi_photo_intent_' . substr( hash( 'sha256', $token_hash ), 0, 16 );

		if ( ! function_exists( 'get_transient' ) || ! function_exists( 'set_transient' ) ) {
			return true;
		}

		$count = (int) get_transient( $key );
		if ( $count >= PhotoLimits::INTENT_RATE_MAX ) {
			return false;
		}

		set_transient( $key, $count + 1, PhotoLimits::INTENT_RATE_WINDOW );

		return true;
	}
}
