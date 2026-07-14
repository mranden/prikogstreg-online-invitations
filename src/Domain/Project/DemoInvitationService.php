<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

use PrikOgStreg\OnlineInvitations\Database\Repositories\EventRepository;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\MyAccount\Endpoints;

/**
 * Owner demo-to-self with rate limiting — no real RSVP or guest stats.
 */
final class DemoInvitationService {

	private const RATE_LIMIT_SECONDS = 300;

	public function __construct(
		private EventRepository $events,
		private DeliveryQueueService $queue
	) {}

	/**
	 * @param array<string, mixed> $project
	 * @return array{success:bool,error?:string,preview_url?:string}
	 */
	public function send_demo( array $project, int $owner_user_id ): array {
		if ( ! ProjectEntitlement::can_edit_project( $project ) ) {
			return [ 'success' => false, 'error' => 'entitlement_denied' ];
		}

		if ( (int) ( $project['user_id'] ?? 0 ) !== $owner_user_id ) {
			return [ 'success' => false, 'error' => 'not_owner' ];
		}

		$project_id = (int) $project['project_id'];
		$cache_key  = 'pks_oi_demo_sent_' . $project_id;
		$last_sent  = get_transient( $cache_key );
		if ( false !== $last_sent ) {
			return [ 'success' => false, 'error' => 'demo_rate_limited' ];
		}

		$preview_url = Endpoints::project_url( $project_id, 'preview' ) . '?demo=1';
		$scope       = (string) time();
		$delivery_id = $this->queue->queue_demo( $project, $scope );
		if ( $delivery_id <= 0 ) {
			return [ 'success' => false, 'error' => 'queue_failed' ];
		}

		do_action( 'pks_oi_demo_invitation_ready', $project_id, $owner_user_id, $preview_url );
		set_transient( $cache_key, time(), self::RATE_LIMIT_SECONDS );

		$this->events->insert(
			[
				'project_id'    => $project_id,
				'actor_type'    => 'customer',
				'event_type'    => 'demo_invitation_sent',
				'metadata_json' => json_encode( [ 'preview_url' => $preview_url ], JSON_UNESCAPED_SLASHES ) ?: '{}',
			]
		);

		return [ 'success' => true, 'preview_url' => $preview_url ];
	}
}
