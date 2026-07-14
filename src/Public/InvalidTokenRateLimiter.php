<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

/**
 * Limits repeated invalid token lookups per client.
 */
final class InvalidTokenRateLimiter {

	private const WINDOW_SECONDS = 60;

	private const MAX_ATTEMPTS = 20;

	public function is_limited( string $client_key ): bool {
		$key   = 'pks_oi_invalid_token_' . hash( 'sha256', $client_key );
		$count = (int) get_transient( $key );

		return $count >= self::MAX_ATTEMPTS;
	}

	public function record_failure( string $client_key ): void {
		$key   = 'pks_oi_invalid_token_' . hash( 'sha256', $client_key );
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, self::WINDOW_SECONDS );
	}

	public function client_key_from_request(): string {
		$ip = (string) ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );

		return $ip;
	}
}
