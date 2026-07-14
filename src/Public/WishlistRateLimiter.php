<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

/**
 * Limits wishlist mutation attempts per bearer token.
 */
final class WishlistRateLimiter {

	private const WINDOW_SECONDS = 60;

	private const MAX_ATTEMPTS = 20;

	public function is_limited( string $token_hash ): bool {
		$key   = 'pks_oi_wishlist_post_' . hash( 'sha256', $token_hash );
		$count = (int) get_transient( $key );

		return $count >= self::MAX_ATTEMPTS;
	}

	public function record_attempt( string $token_hash ): void {
		$key   = 'pks_oi_wishlist_post_' . hash( 'sha256', $token_hash );
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, self::WINDOW_SECONDS );
	}
}
