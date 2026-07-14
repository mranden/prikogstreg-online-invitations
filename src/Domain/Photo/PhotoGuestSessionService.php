<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Photo;

use PrikOgStreg\OnlineInvitations\Security\InvitationToken;

/**
 * HttpOnly signed cookie session for authorized photo guests.
 */
final class PhotoGuestSessionService {

	public const COOKIE_NAME   = 'pks_oi_photo_sess';
	public const TTL_SECONDS   = 14400;
	private const SCHEME       = 'pks_oi_photo_guest';

	/**
	 * @param array<string, mixed> $project
	 */
	public function issue( array $project, string $share_token_hash ): void {
		$payload = [
			'project_id'           => (int) ( $project['project_id'] ?? 0 ),
			'share_token_hash'     => $share_token_hash,
			'share_token_version'  => (int) ( $project['photo_share_token_version'] ?? 1 ),
			'code_version'         => (int) ( $project['photo_access_code_version'] ?? 0 ),
			'nonce'                => bin2hex( random_bytes( 8 ) ),
			'exp'                  => time() + self::TTL_SECONDS,
		];

		$token = $this->sign( $payload );
		if ( '' === $token ) {
			return;
		}

		$this->set_cookie( $token );
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array<string, mixed>|null
	 */
	public function validate( array $project, string $share_token_hash ): ?array {
		$raw = $this->read_cookie();
		if ( '' === $raw ) {
			return null;
		}

		$payload = $this->verify( $raw );
		if ( null === $payload ) {
			return null;
		}

		if ( (int) ( $payload['project_id'] ?? 0 ) !== (int) ( $project['project_id'] ?? 0 ) ) {
			return null;
		}

		if ( (string) ( $payload['share_token_hash'] ?? '' ) !== $share_token_hash ) {
			return null;
		}

		if ( (int) ( $payload['share_token_version'] ?? 0 ) !== (int) ( $project['photo_share_token_version'] ?? 0 ) ) {
			return null;
		}

		if ( (int) ( $payload['code_version'] ?? 0 ) !== (int) ( $project['photo_access_code_version'] ?? 0 ) ) {
			return null;
		}

		if ( time() > (int) ( $payload['exp'] ?? 0 ) ) {
			return null;
		}

		return $payload;
	}

	public function clear(): void {
		if ( ! headers_sent() && function_exists( 'setcookie' ) ) {
			setcookie(
				self::COOKIE_NAME,
				'',
				[
					'expires'  => time() - 3600,
					'path'     => '/',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				]
			);
		}
	}

	public function client_key(): string {
		$ip = '';
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = (string) $_SERVER['REMOTE_ADDR'];
		}

		return substr( hash( 'sha256', $ip ), 0, 16 );
	}

	public function session_key( array $session ): string {
		$nonce = (string) ( $session['nonce'] ?? '' );

		return 'pks-oi-photo-share-' . substr( hash( 'sha256', $nonce ), 0, 16 );
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function sign( array $payload ): string {
		$json = wp_json_encode( $payload );
		if ( ! is_string( $json ) ) {
			return '';
		}

		$encoded = rtrim( strtr( base64_encode( $json ), '+/', '-_' ), '=' );
		$sig     = hash_hmac( 'sha256', $encoded, $this->secret() );

		return $encoded . '.' . $sig;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function verify( string $token ): ?array {
		if ( ! str_contains( $token, '.' ) ) {
			return null;
		}

		[ $encoded, $sig ] = explode( '.', $token, 2 );
		if ( ! hash_equals( hash_hmac( 'sha256', $encoded, $this->secret() ), $sig ) ) {
			return null;
		}

		$json = base64_decode( strtr( $encoded, '-_', '+/' ), true );
		if ( ! is_string( $json ) ) {
			return null;
		}

		$payload = json_decode( $json, true );

		return is_array( $payload ) ? $payload : null;
	}

	private function read_cookie(): string {
		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::COOKIE_NAME ] ) );
	}

	private function set_cookie( string $token ): void {
		if ( headers_sent() || ! function_exists( 'setcookie' ) ) {
			return;
		}

		setcookie(
			self::COOKIE_NAME,
			$token,
			[
				'expires'  => time() + self::TTL_SECONDS,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			]
		);
	}

	private function secret(): string {
		if ( function_exists( 'wp_salt' ) ) {
			return (string) wp_salt( self::SCHEME );
		}

		return 'pks-oi-photo-guest-test-secret';
	}
}
