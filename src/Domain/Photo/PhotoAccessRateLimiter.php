<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Photo;

/**
 * Brute-force protection for photo access code verification.
 */
final class PhotoAccessRateLimiter {

	public const MAX_FAILURES = 8;

	public const WINDOW_SECONDS = 900;

	public function allow( string $token_hash, string $client_key ): bool {
		return $this->count( $token_hash, $client_key ) < self::MAX_FAILURES;
	}

	public function record_failure( string $token_hash, string $client_key ): void {
		if ( ! function_exists( 'get_transient' ) || ! function_exists( 'set_transient' ) ) {
			return;
		}

		$key   = $this->key( $token_hash, $client_key );
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, self::WINDOW_SECONDS );
	}

	public function reset( string $token_hash, string $client_key ): void {
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( $this->key( $token_hash, $client_key ) );
		}
	}

	private function count( string $token_hash, string $client_key ): int {
		if ( ! function_exists( 'get_transient' ) ) {
			return 0;
		}

		return (int) get_transient( $this->key( $token_hash, $client_key ) );
	}

	private function key( string $token_hash, string $client_key ): string {
		return 'pks_oi_photo_code_' . substr( hash( 'sha256', $token_hash . '|' . $client_key ), 0, 16 );
	}
}
