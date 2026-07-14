<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\SchedulerMeta;
use PrikOgStreg\OnlineInvitations\Scheduling\ActionSchedulerBridge;
use PrikOgStreg\OnlineInvitations\Storage\ProjectStorage;

/**
 * Controlled hard-delete initiated by support staff or customer erasure.
 */
final class ProjectHardDeleteService {

	public function __construct(
		private ProjectRepository $projects,
		private DeliveryQueueService $queue,
		private ProjectLifecycleAudit $audit,
		private ProjectStorage $project_storage
	) {}

	/**
	 * @param array<string, mixed> $project
	 */
	public function delete( array $project, string $reason = '', string $source = 'admin' ): ProjectDeleteResult {
		$project_id = (int) ( $project['project_id'] ?? 0 );
		if ( $project_id <= 0 ) {
			return ProjectDeleteResult::failed( 'invalid_project' );
		}

		$current = $this->projects->find_by_id( $project_id );
		if ( ! is_array( $current ) ) {
			return ProjectDeleteResult::already_removed();
		}

		if ( ProjectStatus::DELETED === (string) ( $current['status'] ?? '' ) ) {
			return ProjectDeleteResult::already_removed();
		}

		$errors = [];
		$this->queue->cancel_all_project_jobs( $project_id );
		ActionSchedulerBridge::unschedule( SchedulerMeta::SCAN_EXPIRATIONS, [] );

		$this->audit->record_admin(
			$project_id,
			'project.deleted',
			[
				'reason' => $reason,
				'source' => $source,
			]
		);

		$storage_uuid = (string) ( $current['storage_uuid'] ?? '' );
		if ( '' !== $storage_uuid && ! $this->project_storage->delete_project_storage( $storage_uuid ) ) {
			$errors[] = 'storage_delete_failed';
		}

		if ( function_exists( 'wp_delete_post' ) ) {
			$deleted = wp_delete_post( $project_id, true );
			if ( false === $deleted ) {
				$errors[] = 'post_delete_failed';
			}
		} else {
			do_action( 'pks_oi_before_project_domain_cleanup', $project_id );
		}

		if ( is_array( $this->projects->find_by_id( $project_id ) ) ) {
			$errors[] = 'domain_cleanup_incomplete';
		}

		return ProjectDeleteResult::completed( $errors );
	}
}
