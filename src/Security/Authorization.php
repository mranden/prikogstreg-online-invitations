<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Security;

use PrikOgStreg\OnlineInvitations\Admin\Capabilities;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectEntitlement;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;

/**
 * Central project access checks for My Account and support views.
 */
final class Authorization {

	public function __construct(
		private ProjectRepository $projects
	) {}

	public function current_user_id(): int {
		return function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
	}

	public function has_support_capability(): bool {
		return function_exists( 'current_user_can' ) && current_user_can( Capabilities::SUPPORT );
	}

	public function has_manage_own_capability(): bool {
		return function_exists( 'current_user_can' ) && current_user_can( Capabilities::MANAGE_OWN );
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public function is_owner( array $project ): bool {
		return (int) ( $project['user_id'] ?? 0 ) === $this->current_user_id();
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public function is_support_view( array $project ): bool {
		return $this->has_support_capability() && ! $this->is_owner( $project );
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public function can_view_project( array $project ): bool {
		if ( $this->is_owner( $project ) && $this->has_manage_own_capability() ) {
			return true;
		}

		return $this->has_support_capability();
	}

	/**
	 * Loads a project when the current user may view it; otherwise null (uniform denial).
	 *
	 * @return array<string, mixed>|null
	 */
	public function resolve_viewable_project( int $project_id ): ?array {
		if ( $project_id <= 0 || $this->current_user_id() <= 0 ) {
			return null;
		}

		$project = $this->projects->find_by_id( $project_id );
		if ( ! is_array( $project ) ) {
			return null;
		}

		if ( ! $this->can_view_project( $project ) ) {
			return null;
		}

		return $project;
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public function can_edit_project( array $project ): bool {
		if ( ! $this->can_view_project( $project ) ) {
			return false;
		}

		return ProjectEntitlement::can_edit_project( $project );
	}
}
