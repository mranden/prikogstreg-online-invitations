<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\MyAccount;

use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;

/**
 * Theme-facing presentation data for My Account integration.
 *
 * Keeps project counts and navigation summaries available through WordPress
 * filters without requiring themes to query plugin tables directly.
 */
final class AccountPresentation {

	public function __construct(
		private readonly ProjectRepository $projects
	) {
	}

	public function register(): void {
		require_once PKS_OI_PLUGIN_PATH . 'src/MyAccount/theme-api.php';

		add_filter( 'pks_oi_user_project_count', [ $this, 'filter_user_project_count' ], 10, 2 );
		add_filter( 'pks_oi_user_projects_nav', [ $this, 'filter_user_projects_nav' ], 10, 3 );
	}

	/**
	 * Supply the active project count for a customer account.
	 *
	 * @param int $count   Count from earlier filters; ignored when this callback runs as the default provider.
	 * @param int $user_id WordPress user ID.
	 */
	public function filter_user_project_count( int $count, int $user_id ): int {
		if ( $user_id <= 0 ) {
			return 0;
		}

		return $this->projects->count_active_for_user( $user_id );
	}

	/**
	 * @param array<string, mixed> $nav
	 * @return array{
	 *     count:int,
	 *     list_url:string,
	 *     primary_url:string,
	 *     projects:list<array{
	 *         project_id:int,
	 *         title:string,
	 *         url:string,
	 *         status:string,
	 *         publication_status:string,
	 *         updated_at:string
	 *     }>
	 * }
	 */
	public function filter_user_projects_nav( array $nav, int $user_id, int $limit ): array {
		if ( $user_id <= 0 ) {
			return $this->empty_nav();
		}

		$result   = $this->projects->list_summary_for_user( $user_id, 1, $limit );
		$list_url = Endpoints::base_url();
		$items    = [];

		foreach ( $result['items'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$project_id = (int) ( $row['project_id'] ?? 0 );
			if ( $project_id <= 0 ) {
				continue;
			}

			$items[] = [
				'project_id'         => $project_id,
				'title'              => $this->project_title( $row ),
				'url'                => Endpoints::project_url( $project_id ),
				'status'             => (string) ( $row['status'] ?? '' ),
				'publication_status' => (string) ( $row['publication_status'] ?? '' ),
				'updated_at'         => (string) ( $row['updated_at_utc'] ?? '' ),
			];
		}

		$primary_url = $list_url;
		if ( [] !== $items ) {
			$primary_url = (string) ( $items[0]['url'] ?? $list_url );
		}

		return [
			'count'       => (int) ( $result['total'] ?? 0 ),
			'list_url'    => $list_url,
			'primary_url' => $primary_url,
			'projects'    => $items,
		];
	}

	/**
	 * @return array{
	 *     count:int,
	 *     list_url:string,
	 *     primary_url:string,
	 *     projects:list<array<string,mixed>>
	 * }
	 */
	private function empty_nav(): array {
		$list_url = Endpoints::base_url();

		return [
			'count'       => 0,
			'list_url'    => $list_url,
			'primary_url' => $list_url,
			'projects'    => [],
		];
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function project_title( array $project ): string {
		$title = trim( (string) ( $project['event_title'] ?? '' ) );
		if ( '' !== $title ) {
			return $title;
		}

		return sprintf(
			/* translators: %d: project ID */
			__( 'Invitation project #%d', 'prikogstreg-online-invitations' ),
			(int) ( $project['project_id'] ?? 0 )
		);
	}
}
