<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

/**
 * Customer-initiated archive — read-only retention without deletion.
 */
final class ProjectArchiveService {

	public function __construct(
		private ProjectRepository $projects,
		private DeliveryQueueService $queue,
		private ProjectLifecycleAudit $audit
	) {}

	/**
	 * @param array<string, mixed> $project
	 */
	public function archive( array $project, string $reason = '' ): bool {
		$project_id = (int) ( $project['project_id'] ?? 0 );
		if ( $project_id <= 0 ) {
			return false;
		}

		if ( ProjectStatus::ARCHIVED === (string) ( $project['status'] ?? '' ) ) {
			return true;
		}

		if ( in_array(
			(string) ( $project['status'] ?? '' ),
			[ ProjectStatus::DELETED, ProjectStatus::RESTRICTED ],
			true
		) ) {
			return false;
		}

		$this->projects->update(
			$project_id,
			[
				'status'             => ProjectStatus::ARCHIVED,
				'publication_status' => PublicationStatus::UNPUBLISHED,
				'updated_at_utc'     => UtcDateTime::now(),
			]
		);

		$this->queue->cancel_all_project_jobs( $project_id );
		$this->audit->record_admin(
			$project_id,
			'project.archived',
			[
				'reason' => $reason,
				'source' => 'customer',
			]
		);

		do_action( 'pks_oi_project_archived', $project_id );

		return true;
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public function restore( array $project, string $reason = '' ): bool {
		$project_id = (int) ( $project['project_id'] ?? 0 );
		if ( $project_id <= 0 ) {
			return false;
		}

		if ( ProjectStatus::ARCHIVED !== (string) ( $project['status'] ?? '' ) ) {
			return true;
		}

		$this->projects->update(
			$project_id,
			[
				'status'         => ProjectStatus::ACTIVE,
				'updated_at_utc' => UtcDateTime::now(),
			]
		);

		$this->audit->record_admin(
			$project_id,
			'project.restored_from_archive',
			[
				'reason' => $reason,
				'source' => 'customer',
			]
		);

		return true;
	}
}
