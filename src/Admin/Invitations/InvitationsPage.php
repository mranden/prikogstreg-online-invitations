<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin\Invitations;

use PrikOgStreg\OnlineInvitations\Admin\Capabilities;
use PrikOgStreg\OnlineInvitations\Admin\ProjectAdminListViewModel;
use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;

/**
 * Renders the invitations list and routes to the detail screen.
 */
final class InvitationsPage {

	public function __construct(
		private ProjectAdminListViewModel $list_view_model,
		private InvitationDetailPage $detail_page,
		private TemplateLoader $templates
	) {}

	public function render(): void {
		if ( ! current_user_can( Capabilities::VIEW ) ) {
			wp_die( esc_html__( 'You do not have permission to view invitation projects.', 'prikogstreg-online-invitations' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = sanitize_key( (string) ( $_GET['action'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$project_id = isset( $_GET['project_id'] ) ? (int) $_GET['project_id'] : 0;

		if ( 'view' === $action && $project_id > 0 ) {
			$this->detail_page->render( $project_id );

			return;
		}

		$this->render_list();
	}

	private function render_list(): void {
		$table = new InvitationListTable( $this->list_view_model );
		$table->prepare_items();

		echo '<div class="wrap pks-oi-admin-projects">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Invitations', 'prikogstreg-online-invitations' ) . '</h1>';
		echo '<hr class="wp-header-end" />';
		settings_errors( 'pks_oi_admin' );

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="' . esc_attr( ProjectAdminListViewModel::PAGE_SLUG ) . '" />';
		$table->search_box( __( 'Search invitations', 'prikogstreg-online-invitations' ), 'pks-oi-projects' );
		$table->display();
		echo '</form>';
		echo '</div>';
	}
}
