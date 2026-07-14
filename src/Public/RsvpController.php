<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

use PrikOgStreg\OnlineInvitations\Domain\Project\PublicEntitlement;
use PrikOgStreg\OnlineInvitations\Domain\Rsvp\RsvpService;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Public RSVP submission via REST.
 */
final class RsvpController {

	public const REST_NAMESPACE = 'prikogstreg-online-invitations/v1';

	public function __construct(
		private TokenResolver $resolver,
		private RsvpService $rsvp,
		private RsvpRateLimiter $rate_limiter
	) {}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/public/(?P<token>[A-Za-z0-9_-]+)/rsvp',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_submit' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'token' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_submit( WP_REST_Request $request ) {
		$raw_token = rawurldecode( (string) $request->get_param( 'token' ) );
		if ( ! InvitationToken::is_valid_format( $raw_token ) ) {
			return $this->error_response( 'invalid_token', 404 );
		}

		$token_hash = InvitationToken::hash( $raw_token );
		if ( $this->rate_limiter->is_limited( $token_hash ) ) {
			return $this->error_response( 'rate_limited', 429 );
		}

		$resolution = $this->resolver->resolve( $raw_token );
		if ( null === $resolution || ! PublicEntitlement::is_publicly_available( $resolution->project() ) ) {
			return $this->error_response( 'unavailable', 404 );
		}

		$this->rate_limiter->record_attempt( $token_hash );

		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_body_params();
		}
		if ( ! is_array( $body ) ) {
			$body = [];
		}

		$idempotency_key = $this->resolve_idempotency_key( $request, $body );
		$client_key      = $this->rate_limiter->client_key_from_request();

		if ( $resolution->is_personal() ) {
			$result = $this->rsvp->submit_personal( $resolution, $body, $idempotency_key );
		} else {
			$result = $this->rsvp->submit_generic( $resolution, $body, $idempotency_key, $client_key );
		}

		if ( empty( $result['success'] ) ) {
			return $this->map_service_error( (string) ( $result['error'] ?? 'unknown' ) );
		}

		$response = [
			'success' => true,
			'message' => (string) ( $result['message'] ?? '' ),
			'replayed' => ! empty( $result['replayed'] ),
		];

		if ( ! empty( $result['invitation_url'] ) ) {
			$response['invitation_url'] = (string) $result['invitation_url'];
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * @param array<string, mixed> $body
	 */
	private function resolve_idempotency_key( WP_REST_Request $request, array $body ): string {
		$header = (string) $request->get_header( 'x-pks-oi-idempotency-key' );
		if ( '' !== trim( $header ) ) {
			return sanitize_text_field( $header );
		}

		$key = (string) ( $body['idempotency_key'] ?? '' );

		return sanitize_text_field( $key );
	}

	private function map_service_error( string $code ): WP_Error {
		$status = match ( $code ) {
			'rate_limited'     => 429,
			'deadline_closed'  => 403,
			'unavailable'      => 404,
			'invalid_context'  => 404,
			default            => 400,
		};

		$messages = [
			'invalid_attending'      => __( 'Please choose whether you are attending.', 'prikogstreg-online-invitations' ),
			'missing_attendee_count' => __( 'Please enter how many people are attending.', 'prikogstreg-online-invitations' ),
			'invalid_attendee_count' => __( 'The attendee count is not valid.', 'prikogstreg-online-invitations' ),
			'missing_display_name'   => __( 'Please enter your name.', 'prikogstreg-online-invitations' ),
			'invalid_email'          => __( 'The e-mail address is not valid.', 'prikogstreg-online-invitations' ),
			'deadline_closed'        => __( 'The RSVP deadline has passed. Your response can no longer be changed.', 'prikogstreg-online-invitations' ),
			'unavailable'            => __( 'This invitation is not available.', 'prikogstreg-online-invitations' ),
			'rate_limited'           => __( 'Too many requests. Please wait a moment and try again.', 'prikogstreg-online-invitations' ),
		];

		$message = $messages[ $code ] ?? __( 'We could not save your response. Please try again.', 'prikogstreg-online-invitations' );

		return new WP_Error( 'pks_oi_rsvp_' . $code, $message, [ 'status' => $status ] );
	}

	private function error_response( string $code, int $status ): WP_Error {
		return new WP_Error(
			'pks_oi_rsvp_' . $code,
			__( 'This invitation is not available.', 'prikogstreg-online-invitations' ),
			[ 'status' => $status ]
		);
	}
}
