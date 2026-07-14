<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\WooCommerce\Orders\OrderRefundDetector;

/**
 * Restores restricted projects after admin review.
 */
final class ProjectRestoreService {

	public function __construct(
		private ProjectRepository $projects,
		private OrderRefundDetector $refunds,
		private ProjectLifecycleAudit $audit
	) {}

	/**
	 * @param array<string, mixed> $project
	 * @return array{success:bool,error?:string}
	 */
	public function restore( array $project, string $reason = '' ): array {
		$project_id = (int) ( $project['project_id'] ?? 0 );
		if ( $project_id <= 0 ) {
			return [ 'success' => false, 'error' => 'invalid_project' ];
		}

		if ( ProjectStatus::RESTRICTED !== (string) ( $project['status'] ?? '' ) ) {
			return [ 'success' => false, 'error' => 'not_restricted' ];
		}

		if ( $this->refunds->is_invitation_line_fully_refunded( $project ) ) {
			return [ 'success' => false, 'error' => 'still_refunded' ];
		}

		$this->projects->update(
			$project_id,
			[
				'status'            => ProjectStatus::ACTIVE,
				'restricted_at_utc' => null,
			]
		);

		$this->audit->record_admin(
			$project_id,
			'project.restored',
			[ 'reason' => $reason ]
		);

		do_action( 'pks_oi_project_restored', $project_id );

		return [ 'success' => true ];
	}
}
