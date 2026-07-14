<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Photo;

use PrikOgStreg\OnlineInvitations\Public\TokenResolution;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;

/**
 * Short-lived HMAC-signed upload intents.
 */
final class PhotoUploadIntentService {

	private const SCHEME = 'pks_oi_photo_upload';

	/**
	 * @return array{success:bool,error?:string,intent?:string,expires_at?:int,max_files?:int}
	 */
	public function issue( int $project_id, int $guest_id, string $token_hash, int $max_files = PhotoLimits::MAX_FILES_PER_REQUEST ): array {
		if ( $project_id <= 0 || '' === $token_hash ) {
			return [ 'success' => false, 'error' => 'invalid_context' ];
		}

		$max_files = max( 1, min( PhotoLimits::MAX_FILES_PER_REQUEST, $max_files ) );
		$expires   = time() + PhotoLimits::INTENT_TTL_SECONDS;
		$payload   = wp_json_encode(
			[
				'project_id' => $project_id,
				'guest_id'   => $guest_id,
				'token_hash' => $token_hash,
				'exp'        => $expires,
				'max_files'  => $max_files,
				'nonce'      => bin2hex( random_bytes( 8 ) ),
			]
		);

		if ( ! is_string( $payload ) ) {
			return [ 'success' => false, 'error' => 'intent_failed' ];
		}

		$encoded = rtrim( strtr( base64_encode( $payload ), '+/', '-_' ), '=' );
		$sig     = hash_hmac( 'sha256', $encoded, $this->secret() );

		return [
			'success'    => true,
			'intent'     => $encoded . '.' . $sig,
			'expires_at' => $expires,
			'max_files'  => $max_files,
		];
	}

	/**
	 * @return array{success:bool,error?:string,payload?:array<string,mixed>}
	 */
	public function verify( string $intent_token, TokenResolution $resolution, string $raw_token ): array {
		$intent_token = trim( $intent_token );
		if ( '' === $intent_token || ! str_contains( $intent_token, '.' ) ) {
			return [ 'success' => false, 'error' => 'invalid_intent' ];
		}

		[ $encoded, $sig ] = explode( '.', $intent_token, 2 );
		if ( ! hash_equals( hash_hmac( 'sha256', $encoded, $this->secret() ), $sig ) ) {
			return [ 'success' => false, 'error' => 'invalid_intent' ];
		}

		$json = base64_decode( strtr( $encoded, '-_', '+/' ), true );
		if ( ! is_string( $json ) ) {
			return [ 'success' => false, 'error' => 'invalid_intent' ];
		}

		$payload = json_decode( $json, true );
		if ( ! is_array( $payload ) ) {
			return [ 'success' => false, 'error' => 'invalid_intent' ];
		}

		$project = $resolution->project();
		if ( (int) ( $payload['project_id'] ?? 0 ) !== (int) ( $project['project_id'] ?? 0 ) ) {
			return [ 'success' => false, 'error' => 'wrong_project' ];
		}

		if ( InvitationToken::hash( $raw_token ) !== (string) ( $payload['token_hash'] ?? '' ) ) {
			return [ 'success' => false, 'error' => 'wrong_token' ];
		}

		if ( time() > (int) ( $payload['exp'] ?? 0 ) ) {
			return [ 'success' => false, 'error' => 'expired_intent' ];
		}

		$guest_id = (int) ( $payload['guest_id'] ?? 0 );
		$guest    = $resolution->guest();
		if ( $resolution->is_personal() ) {
			if ( ! is_array( $guest ) || (int) ( $guest['guest_id'] ?? 0 ) !== $guest_id ) {
				return [ 'success' => false, 'error' => 'wrong_guest' ];
			}
		} elseif ( $guest_id <= 0 ) {
			return [ 'success' => false, 'error' => 'missing_guest' ];
		}

		return [ 'success' => true, 'payload' => $payload ];
	}

	private function secret(): string {
		if ( function_exists( 'wp_salt' ) ) {
			return (string) wp_salt( self::SCHEME );
		}

		return 'pks-oi-photo-upload-test-secret';
	}
}
