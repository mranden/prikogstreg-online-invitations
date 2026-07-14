<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;

/**
 * Customer-initiated permanent project deletion.
 */
final class ProjectCustomerDeleteService {

	public const CONFIRMATION_PHRASE = 'DELETE';

	public function __construct(
		private ProjectRepository $projects,
		private ProjectHardDeleteService $hard_delete
	) {}

	/**
	 * @param array<string, mixed> $project
	 * @return array{success:bool,errors:list<string>}
	 */
	public function request_delete( array $project, int $user_id, string $confirmation ): array {
		$project_id = (int) ( $project['project_id'] ?? 0 );
		if ( $project_id <= 0 || ! $this->projects->owned_by( $project_id, $user_id ) ) {
			return [
				'success' => false,
				'errors'  => [ 'forbidden' ],
			];
		}

		if ( self::CONFIRMATION_PHRASE !== strtoupper( trim( $confirmation ) ) ) {
			return [
				'success' => false,
				'errors'  => [ 'confirmation_required' ],
			];
		}

		$result = $this->hard_delete->delete( $project, 'customer_request', 'customer' );

		return [
			'success' => $result->success,
			'errors'  => $result->errors,
		];
	}
}
