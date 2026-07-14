<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

/**
 * Marks projects expired without hard-deleting data.
 */
final class ProjectExpireService {

	public function __construct(
		private ProjectRepository $projects,
		private DeliveryQueueService $queue,
		private ProjectLifecycleAudit $audit
	) {}

	public function expire_if_due( int $project_id ): bool {
		$project = $this->projects->find_by_id( $project_id );
		if ( ! is_array( $project ) ) {
			return false;
		}

		return $this->expire_project( $project );
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public function expire_project( array $project ): bool {
		$project_id = (int) ( $project['project_id'] ?? 0 );
		if ( $project_id <= 0 ) {
			return false;
		}

		if ( ProjectStatus::EXPIRED === (string) ( $project['status'] ?? '' ) ) {
			return true;
		}

		if ( ProjectStatus::ACTIVE !== (string) ( $project['status'] ?? '' ) ) {
			return false;
		}

		if ( ! ProjectExpiration::is_past_effective_expiry( $project ) ) {
			return false;
		}

		$this->projects->update(
			$project_id,
			[
				'status'             => ProjectStatus::EXPIRED,
				'expired_at_utc'     => UtcDateTime::now(),
				'publication_status' => PublicationStatus::UNPUBLISHED,
			]
		);

		$this->queue->cancel_queued_for_project( $project_id );
		$this->audit->record_admin( $project_id, 'project.expired', [] );

		do_action( 'pks_oi_project_expired', $project_id );

		return true;
	}
}
