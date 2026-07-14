<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Photo;

/**
 * Short-lived HMAC-signed upload intents for photo share sessions.
 */
final class PhotoShareUploadIntentService {

	private const SCHEME = 'pks_oi_photo_share_upload';

	/**
	 * @param array<string, mixed> $session
	 * @return array{success:bool,error?:string,intent?:string,expires_at?:int,max_files?:int}
	 */
	public function issue(
		array $project,
		array $session,
		string $share_token_hash,
		int $guest_id,
		int $max_files = PhotoLimits::MAX_FILES_PER_REQUEST
	): array {
		$project_id = (int) ( $project['project_id'] ?? 0 );
		if ( $project_id <= 0 || '' === $share_token_hash ) {
			return [ 'success' => false, 'error' => 'invalid_context' ];
		}

		$max_files = max( 1, min( PhotoLimits::MAX_FILES_PER_REQUEST, $max_files ) );
		$expires   = time() + PhotoLimits::INTENT_TTL_SECONDS;
		$payload   = wp_json_encode(
			[
				'project_id'          => $project_id,
				'guest_id'            => $guest_id,
				'share_token_hash'    => $share_token_hash,
				'share_token_version' => (int) ( $project['photo_share_token_version'] ?? 1 ),
				'code_version'        => (int) ( $project['photo_access_code_version'] ?? 0 ),
				'session_nonce'       => (string) ( $session['nonce'] ?? '' ),
				'exp'                 => $expires,
				'max_files'           => $max_files,
				'nonce'               => bin2hex( random_bytes( 8 ) ),
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
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $session
	 * @return array{success:bool,error?:string,payload?:array<string,mixed>}
	 */
	public function verify(
		string $intent_token,
		array $project,
		array $session,
		string $share_token_hash
	): array {
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

		if ( (int) ( $payload['project_id'] ?? 0 ) !== (int) ( $project['project_id'] ?? 0 ) ) {
			return [ 'success' => false, 'error' => 'wrong_project' ];
		}

		if ( (string) ( $payload['share_token_hash'] ?? '' ) !== $share_token_hash ) {
			return [ 'success' => false, 'error' => 'wrong_token' ];
		}

		if ( (int) ( $payload['share_token_version'] ?? 0 ) !== (int) ( $project['photo_share_token_version'] ?? 0 ) ) {
			return [ 'success' => false, 'error' => 'wrong_token' ];
		}

		if ( (int) ( $payload['code_version'] ?? 0 ) !== (int) ( $project['photo_access_code_version'] ?? 0 ) ) {
			return [ 'success' => false, 'error' => 'wrong_token' ];
		}

		if ( (string) ( $payload['session_nonce'] ?? '' ) !== (string) ( $session['nonce'] ?? '' ) ) {
			return [ 'success' => false, 'error' => 'invalid_session' ];
		}

		if ( time() > (int) ( $payload['exp'] ?? 0 ) ) {
			return [ 'success' => false, 'error' => 'expired_intent' ];
		}

		return [ 'success' => true, 'payload' => $payload ];
	}

	private function secret(): string {
		if ( function_exists( 'wp_salt' ) ) {
			return (string) wp_salt( self::SCHEME );
		}

		return 'pks-oi-photo-share-upload-test-secret';
	}
}
