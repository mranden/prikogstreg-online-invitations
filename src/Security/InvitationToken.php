<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Security;

/**
 * High-entropy URL-safe invitation tokens — only hashes are persisted.
 */
final class InvitationToken {

	public const HASH_ALGO = 'sha256';

	/**
	 * @return array{raw:string,hash:string}
	 */
	public static function generate(): array {
		$raw = self::encode( random_bytes( 32 ) );

		return [
			'raw'  => $raw,
			'hash' => self::hash( $raw ),
		];
	}

	public static function hash( string $raw_token ): string {
		return hash( self::HASH_ALGO, $raw_token );
	}

	public static function is_valid_format( string $raw_token ): bool {
		$length = strlen( $raw_token );

		return $length >= 32
			&& $length <= 64
			&& 1 === preg_match( '/^[A-Za-z0-9_-]+$/', $raw_token );
	}

	public static function encode( string $bytes ): string {
		return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
	}

	public static function public_path( string $raw_token ): string {
		return '/invitation/' . rawurlencode( $raw_token ) . '/';
	}

	public static function public_url( string $raw_token ): string {
		if ( function_exists( 'home_url' ) ) {
			return home_url( self::public_path( $raw_token ) );
		}

		return self::public_path( $raw_token );
	}
}
