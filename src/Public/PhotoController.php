<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoService;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicEntitlement;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Public photo upload intent and multipart upload via REST.
 */
final class PhotoController {

	public const REST_NAMESPACE = 'prikogstreg-online-invitations/v1';

	public function __construct(
		private TokenResolver $resolver,
		private PhotoService $photos
	) {}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/public/(?P<token>[A-Za-z0-9_-]+)/photos/intent',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_intent' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/public/(?P<token>[A-Za-z0-9_-]+)/photos/upload',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_upload' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_intent( WP_REST_Request $request ) {
		$resolution = $this->resolve_request( $request );
		if ( is_wp_error( $resolution ) ) {
			return $resolution;
		}

		$raw_token = rawurldecode( (string) $request->get_param( 'token' ) );
		$body      = $this->request_body( $request );
		$result    = $this->photos->issue_intent( $resolution, $raw_token, $body );

		if ( empty( $result['success'] ) ) {
			return $this->map_service_error( (string) ( $result['error'] ?? 'unknown' ) );
		}

		return new WP_REST_Response(
			[
				'success'    => true,
				'intent'     => (string) ( $result['intent'] ?? '' ),
				'expires_at' => (int) ( $result['expires_at'] ?? 0 ),
				'max_files'  => (int) ( $result['max_files'] ?? 0 ),
			],
			200
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_upload( WP_REST_Request $request ) {
		$resolution = $this->resolve_request( $request );
		if ( is_wp_error( $resolution ) ) {
			return $resolution;
		}

		$intent = (string) $request->get_header( 'x-pks-oi-upload-intent' );
		if ( '' === trim( $intent ) ) {
			$body   = $this->request_body( $request );
			$intent = (string) ( $body['intent'] ?? '' );
		}

		$raw_token = rawurldecode( (string) $request->get_param( 'token' ) );
		$files     = $this->normalized_files();
		$result    = $this->photos->upload( $resolution, $raw_token, $intent, $files );

		if ( empty( $result['success'] ) ) {
			return $this->map_service_error( (string) ( $result['error'] ?? 'unknown' ) );
		}

		return new WP_REST_Response(
			[
				'success'  => true,
				'uploaded' => $result['uploaded'] ?? [],
			],
			200
		);
	}

	/**
	 * @return TokenResolution|WP_Error
	 */
	private function resolve_request( WP_REST_Request $request ) {
		$raw_token = rawurldecode( (string) $request->get_param( 'token' ) );
		if ( ! InvitationToken::is_valid_format( $raw_token ) ) {
			return $this->error_response( 'invalid_token', 404 );
		}

		$resolution = $this->resolver->resolve( $raw_token );
		if ( null === $resolution || ! PublicEntitlement::is_publicly_available( $resolution->project() ) ) {
			return $this->error_response( 'unavailable', 404 );
		}

		return $resolution;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function request_body( WP_REST_Request $request ): array {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_body_params();
		}

		return is_array( $body ) ? $body : [];
	}

	/**
	 * @return list<array{name:string,tmp_name:string,size:int,error:int}>
	 */
	private function normalized_files(): array {
		if ( ! isset( $_FILES['photos'] ) || ! is_array( $_FILES['photos'] ) ) {
			return [];
		}

		$upload = $_FILES['photos'];
		$names  = $upload['name'] ?? [];
		if ( ! is_array( $names ) ) {
			return [
				[
					'name'     => (string) ( $upload['name'] ?? '' ),
					'tmp_name' => (string) ( $upload['tmp_name'] ?? '' ),
					'size'     => (int) ( $upload['size'] ?? 0 ),
					'error'    => (int) ( $upload['error'] ?? UPLOAD_ERR_NO_FILE ),
				],
			];
		}

		$files = [];
		$count = count( $names );
		for ( $i = 0; $i < $count; ++$i ) {
			$files[] = [
				'name'     => (string) ( $upload['name'][ $i ] ?? '' ),
				'tmp_name' => (string) ( $upload['tmp_name'][ $i ] ?? '' ),
				'size'     => (int) ( $upload['size'][ $i ] ?? 0 ),
				'error'    => (int) ( $upload['error'][ $i ] ?? UPLOAD_ERR_NO_FILE ),
			];
		}

		return $files;
	}

	private function map_service_error( string $code ): WP_Error {
		$status = match ( $code ) {
			'rate_limited', 'too_many_files' => 429,
			'photos_disabled', 'unavailable' => 403,
			'invalid_intent', 'expired_intent', 'wrong_token', 'wrong_project' => 403,
			default => 400,
		};

		return new WP_Error( 'pks_oi_photo_' . $code, $this->message_for_code( $code ), [ 'status' => $status ] );
	}

	private function message_for_code( string $code ): string {
		return match ( $code ) {
			'missing_display_name' => __( 'Please enter your name before uploading photos.', 'prikogstreg-online-invitations' ),
			'rate_limited'         => __( 'Too many requests. Please wait a moment and try again.', 'prikogstreg-online-invitations' ),
			'photos_disabled'      => __( 'Photo uploads are not available for this invitation.', 'prikogstreg-online-invitations' ),
			'expired_intent'       => __( 'Your upload session expired. Please try again.', 'prikogstreg-online-invitations' ),
			default                => __( 'We could not upload your photos. Please try again.', 'prikogstreg-online-invitations' ),
		};
	}

	private function error_response( string $code, int $status ): WP_Error {
		return new WP_Error(
			'pks_oi_photo_' . $code,
			__( 'This invitation is not available.', 'prikogstreg-online-invitations' ),
			[ 'status' => $status ]
		);
	}
}
