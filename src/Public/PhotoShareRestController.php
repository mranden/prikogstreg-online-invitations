<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoAccessCodeService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoGuestSessionService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoShareEntitlement;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoShareTokenService;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;
use PrikOgStreg\OnlineInvitations\Storage\FileStreamResponse;
use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST routes for photo share code verification, upload, and gallery.
 */
final class PhotoShareRestController {

	public const REST_NAMESPACE = 'prikogstreg-online-invitations/v1';

	public function __construct(
		private PhotoShareTokenService $share_tokens,
		private PhotoAccessCodeService $access_codes,
		private PhotoGuestSessionService $sessions,
		private PhotoService $photos,
		private InvalidTokenRateLimiter $rate_limiter
	) {}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/photo-share/(?P<token>[A-Za-z0-9_-]+)/verify',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_verify' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/photo-share/(?P<token>[A-Za-z0-9_-]+)/photos/intent',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_intent' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/photo-share/(?P<token>[A-Za-z0-9_-]+)/photos/upload',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_upload' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/photo-share/(?P<token>[A-Za-z0-9_-]+)/wall/gallery',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_wall_gallery' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_gallery' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_verify( WP_REST_Request $request ) {
		$context = $this->resolve_context( $request );
		if ( is_wp_error( $context ) ) {
			return $context;
		}

		/** @var array<string, mixed> $project */
		$project = $context['project'];
		$body    = $this->request_body( $request );
		$code    = (string) ( $body['code'] ?? '' );

		$result = $this->access_codes->verify(
			$project,
			$code,
			(string) $context['share_token_hash'],
			$this->sessions->client_key()
		);

		if ( empty( $result['success'] ) ) {
			return new WP_Error(
				'pks_oi_photo_invalid_code',
				__( 'The photo code is incorrect. Please try again.', 'prikogstreg-online-invitations' ),
				[ 'status' => 'rate_limited' === ( $result['error'] ?? '' ) ? 429 : 403 ]
			);
		}

		$this->sessions->issue( $project, (string) $context['share_token_hash'] );

		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_intent( WP_REST_Request $request ) {
		$context = $this->resolve_authorized_context( $request );
		if ( is_wp_error( $context ) ) {
			return $context;
		}

		$result = $this->photos->issue_share_intent(
			$context['project'],
			$context['session'],
			(string) $context['share_token_hash'],
			$this->request_body( $request )
		);

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
		$context = $this->resolve_authorized_context( $request );
		if ( is_wp_error( $context ) ) {
			return $context;
		}

		$intent = (string) $request->get_header( 'x-pks-oi-upload-intent' );
		if ( '' === trim( $intent ) ) {
			$body   = $this->request_body( $request );
			$intent = (string) ( $body['intent'] ?? '' );
		}

		$result = $this->photos->upload_share(
			$context['project'],
			$context['session'],
			(string) $context['share_token_hash'],
			$intent,
			$this->normalized_files()
		);

		if ( empty( $result['success'] ) ) {
			return $this->map_service_error( (string) ( $result['error'] ?? 'unknown' ) );
		}

		return new WP_REST_Response(
			[
				'success'  => true,
				'uploaded' => $result['uploaded'] ?? [],
				'message'  => PhotoShareEntitlement::auto_approve_enabled( $context['project'] )
					? __( 'Thank you! Your photos were uploaded and added to the photo wall.', 'prikogstreg-online-invitations' )
					: __( 'Thank you! Your photos were uploaded and are awaiting organiser approval.', 'prikogstreg-online-invitations' ),
			],
			200
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_wall_gallery( WP_REST_Request $request ) {
		$context = $this->resolve_context( $request );
		if ( is_wp_error( $context ) ) {
			return $context;
		}

		$project = $context['project'];
		if ( ! PhotoShareEntitlement::is_gallery_public( $project ) ) {
			return new WP_Error(
				'pks_oi_photo_gallery_disabled',
				__( 'The photo wall is not available.', 'prikogstreg-online-invitations' ),
				[ 'status' => 403 ]
			);
		}

		return $this->gallery_response( $request, $project );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_gallery( WP_REST_Request $request ) {
		$context = $this->resolve_authorized_context( $request );
		if ( is_wp_error( $context ) ) {
			return $context;
		}

		$project = $context['project'];
		if ( ! PhotoShareEntitlement::is_gallery_public( $project ) ) {
			return new WP_Error(
				'pks_oi_photo_gallery_disabled',
				__( 'The photo gallery is not available.', 'prikogstreg-online-invitations' ),
				[ 'status' => 403 ]
			);
		}

		return $this->gallery_response( $request, $project );
	}

	/**
	 * @param array<string, mixed> $project
	 * @return WP_REST_Response
	 */
	private function gallery_response( WP_REST_Request $request, array $project ): WP_REST_Response {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, min( 50, (int) $request->get_param( 'per_page' ) ?: 20 ) );
		$result   = $this->photos->list_gallery_page( $project, $page, $per_page );
		$raw      = rawurldecode( (string) $request->get_param( 'token' ) );
		$items    = [];

		foreach ( $result['items'] as $row ) {
			$photo_id = (int) ( $row['photo_id'] ?? 0 );
			$items[]  = [
				'photo_id'    => $photo_id,
				'width'       => (int) ( $row['width'] ?? 0 ),
				'height'      => (int) ( $row['height'] ?? 0 ),
				'stream_url'  => PhotoShareEndpoints::stream_url( $raw, $photo_id ),
				'created_at'  => (string) ( $row['created_at_utc'] ?? '' ),
			];
		}

		return new WP_REST_Response(
			[
				'success'  => true,
				'items'    => $items,
				'total'    => (int) ( $result['total'] ?? 0 ),
				'page'     => (int) ( $result['page'] ?? 1 ),
				'per_page' => (int) ( $result['per_page'] ?? 20 ),
			],
			200
		);
	}

	/**
	 * @return array{project:array<string,mixed>,share_token_hash:string}|WP_Error
	 */
	private function resolve_context( WP_REST_Request $request ) {
		$raw_token = rawurldecode( (string) $request->get_param( 'token' ) );
		if ( ! InvitationToken::is_valid_format( $raw_token ) ) {
			return $this->error_response( 404 );
		}

		$client_key = $this->rate_limiter->client_key_from_request();
		if ( $this->rate_limiter->is_limited( $client_key ) ) {
			return $this->error_response( 404 );
		}

		$project = $this->share_tokens->resolve_by_raw_token( $raw_token );
		if ( ! is_array( $project ) || ! PhotoShareEntitlement::is_available( $project ) ) {
			$this->rate_limiter->record_failure( $client_key );

			return $this->error_response( 404 );
		}

		return [
			'project'          => $project,
			'share_token_hash' => InvitationToken::hash( $raw_token ),
		];
	}

	/**
	 * @return array{project:array<string,mixed>,session:array<string,mixed>,share_token_hash:string}|WP_Error
	 */
	private function resolve_authorized_context( WP_REST_Request $request ) {
		$context = $this->resolve_context( $request );
		if ( is_wp_error( $context ) ) {
			return $context;
		}

		$session = $this->sessions->validate( $context['project'], (string) $context['share_token_hash'] );
		if ( null === $session ) {
			return new WP_Error(
				'pks_oi_photo_session_required',
				__( 'Please enter the photo code to continue.', 'prikogstreg-online-invitations' ),
				[ 'status' => 403 ]
			);
		}

		return [
			'project'          => $context['project'],
			'share_token_hash' => (string) $context['share_token_hash'],
			'session'          => $session,
		];
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
			'photos_disabled', 'unavailable', 'invalid_intent', 'expired_intent', 'wrong_token', 'wrong_project', 'invalid_session' => 403,
			default => 400,
		};

		return new WP_Error( 'pks_oi_photo_' . $code, $this->message_for_code( $code ), [ 'status' => $status ] );
	}

	private function message_for_code( string $code ): string {
		return match ( $code ) {
			'missing_display_name' => __( 'Please enter your name before uploading photos.', 'prikogstreg-online-invitations' ),
			'rate_limited'         => __( 'Too many requests. Please wait a moment and try again.', 'prikogstreg-online-invitations' ),
			'photos_disabled'      => __( 'Photo uploads are not available for this event.', 'prikogstreg-online-invitations' ),
			'expired_intent'       => __( 'Your upload session expired. Please try again.', 'prikogstreg-online-invitations' ),
			default                => __( 'We could not upload your photos. Please try again.', 'prikogstreg-online-invitations' ),
		};
	}

	private function error_response( int $status ): WP_Error {
		return new WP_Error(
			'pks_oi_photo_unavailable',
			__( 'Photo sharing is not available.', 'prikogstreg-online-invitations' ),
			[ 'status' => $status ]
		);
	}
}
