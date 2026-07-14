<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

use PrikOgStreg\OnlineInvitations\Domain\Project\PublicEntitlement;
use PrikOgStreg\OnlineInvitations\Domain\Wishlist\WishlistReservationService;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Public wishlist list/reserve/release via REST.
 */
final class WishlistController {

	public const REST_NAMESPACE = 'prikogstreg-online-invitations/v1';

	public function __construct(
		private TokenResolver $resolver,
		private WishlistReservationService $wishlist,
		private WishlistRateLimiter $rate_limiter
	) {}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/public/(?P<token>[A-Za-z0-9_-]+)/wishlist',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_list' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/public/(?P<token>[A-Za-z0-9_-]+)/wishlist/(?P<item_id>\d+)/reserve',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_reserve' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/public/(?P<token>[A-Za-z0-9_-]+)/wishlist/(?P<item_id>\d+)/release',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_release' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_list( WP_REST_Request $request ) {
		$resolution = $this->resolve_request( $request );
		if ( is_wp_error( $resolution ) ) {
			return $resolution;
		}

		$project = $resolution->project();
		$external_url = trim( (string) ( $project['external_wishlist_url'] ?? '' ) );

		return new WP_REST_Response(
			[
				'success'              => true,
				'items'                => $this->wishlist->list_public_items( $resolution ),
				'external_wishlist_url'=> '' !== $external_url ? esc_url( $external_url ) : '',
			],
			200
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_reserve( WP_REST_Request $request ) {
		$resolution = $this->resolve_request( $request, true );
		if ( is_wp_error( $resolution ) ) {
			return $resolution;
		}

		$body = $this->request_body( $request );
		$result = $this->wishlist->reserve(
			$resolution,
			(int) $request->get_param( 'item_id' ),
			$body,
			$this->resolve_idempotency_key( $request, $body )
		);

		if ( empty( $result['success'] ) ) {
			return $this->map_service_error( (string) ( $result['error'] ?? 'unknown' ) );
		}

		return new WP_REST_Response(
			[
				'success'  => true,
				'item'     => $result['item'] ?? null,
				'replayed' => ! empty( $result['replayed'] ),
			],
			200
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_release( WP_REST_Request $request ) {
		$resolution = $this->resolve_request( $request, true );
		if ( is_wp_error( $resolution ) ) {
			return $resolution;
		}

		$body = $this->request_body( $request );
		$result = $this->wishlist->release(
			$resolution,
			(int) $request->get_param( 'item_id' ),
			$body,
			$this->resolve_idempotency_key( $request, $body )
		);

		if ( empty( $result['success'] ) ) {
			return $this->map_service_error( (string) ( $result['error'] ?? 'unknown' ) );
		}

		return new WP_REST_Response(
			[
				'success'  => true,
				'item'     => $result['item'] ?? null,
				'replayed' => ! empty( $result['replayed'] ),
			],
			200
		);
	}

	/**
	 * @return TokenResolution|WP_Error
	 */
	private function resolve_request( WP_REST_Request $request, bool $mutating = false ) {
		$raw_token = rawurldecode( (string) $request->get_param( 'token' ) );
		if ( ! InvitationToken::is_valid_format( $raw_token ) ) {
			return $this->error_response( 'invalid_token', 404 );
		}

		$token_hash = InvitationToken::hash( $raw_token );
		if ( $mutating && $this->rate_limiter->is_limited( $token_hash ) ) {
			return $this->error_response( 'rate_limited', 429 );
		}

		$resolution = $this->resolver->resolve( $raw_token );
		if ( null === $resolution || ! PublicEntitlement::is_publicly_available( $resolution->project() ) ) {
			return $this->error_response( 'unavailable', 404 );
		}

		if ( $mutating ) {
			$this->rate_limiter->record_attempt( $token_hash );
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
	 * @param array<string, mixed> $body
	 */
	private function resolve_idempotency_key( WP_REST_Request $request, array $body ): string {
		$header = (string) $request->get_header( 'x-pks-oi-idempotency-key' );
		if ( '' !== trim( $header ) ) {
			return sanitize_text_field( $header );
		}

		return sanitize_text_field( (string) ( $body['idempotency_key'] ?? '' ) );
	}

	private function map_service_error( string $code ): WP_Error {
		$status = match ( $code ) {
			'rate_limited'           => 429,
			'unavailable'            => 404,
			'invalid_context'        => 404,
			'item_unavailable'       => 404,
			'wishlist_disabled'      => 403,
			default                  => 400,
		};

		return new WP_Error( 'pks_oi_wishlist_' . $code, $this->message_for_code( $code ), [ 'status' => $status ] );
	}

	private function message_for_code( string $code ): string {
		return match ( $code ) {
			'missing_display_name'   => __( 'Please enter your name before reserving a gift.', 'prikogstreg-online-invitations' ),
			'insufficient_quantity'  => __( 'That gift is no longer available in the requested quantity.', 'prikogstreg-online-invitations' ),
			'wishlist_disabled'      => __( 'The wishlist is not available for this invitation.', 'prikogstreg-online-invitations' ),
			'rate_limited'           => __( 'Too many requests. Please wait a moment and try again.', 'prikogstreg-online-invitations' ),
			default                  => __( 'We could not update the wishlist. Please try again.', 'prikogstreg-online-invitations' ),
		};
	}

	private function error_response( string $code, int $status ): WP_Error {
		return new WP_Error(
			'pks_oi_wishlist_' . $code,
			__( 'This invitation is not available.', 'prikogstreg-online-invitations' ),
			[ 'status' => $status ]
		);
	}
}
