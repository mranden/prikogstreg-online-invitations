<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

/**
 * Restricts projects after refunds or admin action — preserves data.
 */
final class ProjectRestrictionService {

	public function __construct(
		private ProjectRepository $projects,
		private DeliveryQueueService $queue,
		private ProjectLifecycleAudit $audit
	) {}

	/**
	 * @param array<string, mixed> $project
	 */
	public function restrict( array $project, string $reason = '', string $source = 'admin' ): bool {
		$project_id = (int) ( $project['project_id'] ?? 0 );
		if ( $project_id <= 0 ) {
			return false;
		}

		if ( ProjectStatus::RESTRICTED === (string) ( $project['status'] ?? '' ) ) {
			return true;
		}

		$this->projects->update(
			$project_id,
			[
				'status'              => ProjectStatus::RESTRICTED,
				'restricted_at_utc'   => UtcDateTime::now(),
				'publication_status'  => PublicationStatus::UNPUBLISHED,
			]
		);

		$this->queue->cancel_queued_for_project( $project_id );
		$this->audit->record_admin(
			$project_id,
			'project.restricted',
			[
				'reason' => $reason,
				'source' => $source,
			]
		);

		do_action( 'pks_oi_project_restricted', $project_id, $source );

		return true;
	}
}
