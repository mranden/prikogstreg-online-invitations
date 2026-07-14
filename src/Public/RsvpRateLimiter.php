<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

/**
 * Limits RSVP POST attempts per bearer token.
 */
final class RsvpRateLimiter {

	private const WINDOW_SECONDS = 60;

	private const MAX_ATTEMPTS = 10;

	public function is_limited( string $token_hash ): bool {
		$key   = 'pks_oi_rsvp_post_' . hash( 'sha256', $token_hash );
		$count = (int) get_transient( $key );

		return $count >= self::MAX_ATTEMPTS;
	}

	public function record_attempt( string $token_hash ): void {
		$key   = 'pks_oi_rsvp_post_' . hash( 'sha256', $token_hash );
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, self::WINDOW_SECONDS );
	}

	public function client_key_from_request(): string {
		return (string) ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );
	}
}
