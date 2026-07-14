<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin\Photos;

use PrikOgStreg\OnlineInvitations\Admin\Capabilities;
use PrikOgStreg\OnlineInvitations\Admin\ProjectAdminListViewModel;
use PrikOgStreg\OnlineInvitations\Database\Repositories\PhotoRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;

/**
 * Cross-project pending photo moderation list.
 */
final class PhotosAdminPage {

	public function __construct(
		private PhotoRepository $photos,
		private ProjectRepository $projects,
		private TemplateLoader $templates
	) {}

	public function render(): void {
		if ( ! current_user_can( Capabilities::MODERATE_PHOTOS ) ) {
			wp_die( esc_html__( 'You do not have permission to moderate photos.', 'prikogstreg-online-invitations' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page   = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$result = $this->photos->list_admin_pending( $page, 20 );

		$rows = [];
		foreach ( $result['items'] as $item ) {
			$project_id = (int) ( $item['project_id'] ?? 0 );
			$project    = $this->projects->find_by_id( $project_id );
			$rows[]     = [
				'photo_id'          => (int) ( $item['photo_id'] ?? 0 ),
				'project_id'        => $project_id,
				'project_title'     => is_array( $project ) ? (string) ( $project['event_title'] ?? '' ) : '',
				'original_filename' => (string) ( $item['original_filename'] ?? '' ),
				'created_at_utc'    => (string) ( $item['created_at_utc'] ?? '' ),
				'detail_url'        => ProjectAdminListViewModel::detail_url( $project_id, 'photos' ),
			];
		}

		echo '<div class="wrap pks-oi-admin-projects">';
		echo '<h1>' . esc_html__( 'Photo moderation', 'prikogstreg-online-invitations' ) . '</h1>';
		$this->templates->render(
			'admin/photos-list',
			[
				'rows'       => $rows,
				'pagination' => $result,
				'page'       => $page,
			]
		);
		echo '</div>';
	}
}
