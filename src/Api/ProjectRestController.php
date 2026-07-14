<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Api;

use PrikOgStreg\OnlineInvitations\Domain\Project\DemoInvitationService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectEventService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectPublishService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStateService;
use PrikOgStreg\OnlineInvitations\Security\Authorization;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Authenticated project lifecycle REST endpoints.
 */
final class ProjectRestController {

	public const NAMESPACE = 'prikogstreg-online-invitations/v1';

	public function __construct(
		private Authorization $authorization,
		private ProjectStateService $state_service,
		private ProjectEventService $event_service,
		private ProjectPublishService $publish_service,
		private DemoInvitationService $demo_service
	) {}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/projects/(?P<project_id>\d+)/state',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'save_state' ],
				'permission_callback' => [ $this, 'can_edit' ],
				'args'                => [
					'project_id' => [ 'type' => 'integer', 'required' => true ],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/projects/(?P<project_id>\d+)/event',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'save_event' ],
				'permission_callback' => [ $this, 'can_edit' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/projects/(?P<project_id>\d+)/publish',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'publish' ],
				'permission_callback' => [ $this, 'can_edit' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/projects/(?P<project_id>\d+)/unpublish',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'unpublish' ],
				'permission_callback' => [ $this, 'can_edit' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/projects/(?P<project_id>\d+)/demo',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'send_demo' ],
				'permission_callback' => [ $this, 'can_edit' ],
			]
		);
	}

	public function can_edit( WP_REST_Request $request ): bool {
		$project = $this->authorization->resolve_viewable_project( (int) $request['project_id'] );

		return is_array( $project ) && $this->authorization->can_edit_project( $project );
	}

	public function save_state( WP_REST_Request $request ): WP_REST_Response {
		$project = $this->authorization->resolve_viewable_project( (int) $request['project_id'] );
		if ( ! is_array( $project ) ) {
			return new WP_REST_Response( [ 'error' => 'not_found' ], 404 );
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) || ! isset( $params['state'] ) || ! is_array( $params['state'] ) ) {
			return new WP_REST_Response( [ 'error' => 'invalid_payload' ], 400 );
		}

		$expected = (int) ( $params['expected_state_version'] ?? $project['state_version'] ?? 0 );
		$result   = $this->state_service->save_design_state( $project, $params['state'], $expected );

		if ( isset( $result['error'] ) ) {
			return new WP_REST_Response( [ 'error' => $result['error'] ], (int) ( $result['code'] ?? 400 ) );
		}

		return new WP_REST_Response( $result, 200 );
	}

	public function save_event( WP_REST_Request $request ): WP_REST_Response {
		$project = $this->authorization->resolve_viewable_project( (int) $request['project_id'] );
		if ( ! is_array( $project ) ) {
			return new WP_REST_Response( [ 'error' => 'not_found' ], 404 );
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_body_params();
		}

		$result = $this->event_service->save_event_details( $project, is_array( $params ) ? $params : [] );
		if ( empty( $result['success'] ) ) {
			return new WP_REST_Response( [ 'error' => $result['error'] ?? 'save_failed' ], 422 );
		}

		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	public function publish( WP_REST_Request $request ): WP_REST_Response {
		$project = $this->authorization->resolve_viewable_project( (int) $request['project_id'] );
		if ( ! is_array( $project ) ) {
			return new WP_REST_Response( [ 'error' => 'not_found' ], 404 );
		}

		$result = $this->publish_service->publish( $project );
		if ( empty( $result['success'] ) ) {
			return new WP_REST_Response( [ 'error' => $result['error'] ?? 'publish_failed' ], (int) ( $result['code'] ?? 422 ) );
		}

		return new WP_REST_Response( $result, 200 );
	}

	public function unpublish( WP_REST_Request $request ): WP_REST_Response {
		$project = $this->authorization->resolve_viewable_project( (int) $request['project_id'] );
		if ( ! is_array( $project ) ) {
			return new WP_REST_Response( [ 'error' => 'not_found' ], 404 );
		}

		$result = $this->publish_service->unpublish( $project );
		if ( empty( $result['success'] ) ) {
			return new WP_REST_Response( [ 'error' => $result['error'] ?? 'unpublish_failed' ], 422 );
		}

		return new WP_REST_Response( $result, 200 );
	}

	public function send_demo( WP_REST_Request $request ): WP_REST_Response {
		$project = $this->authorization->resolve_viewable_project( (int) $request['project_id'] );
		if ( ! is_array( $project ) ) {
			return new WP_REST_Response( [ 'error' => 'not_found' ], 404 );
		}

		$result = $this->demo_service->send_demo( $project, $this->authorization->current_user_id() );
		if ( empty( $result['success'] ) ) {
			$code = 'demo_rate_limited' === ( $result['error'] ?? '' ) ? 429 : 422;

			return new WP_REST_Response( [ 'error' => $result['error'] ], $code );
		}

		return new WP_REST_Response( $result, 200 );
	}
}
