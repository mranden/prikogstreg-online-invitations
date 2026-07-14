<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin;

use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;

/**
 * WooCommerce admin list and read-only detail for invitation projects.
 */
final class ProjectsAdminScreen {

	public function __construct(
		private ProjectAdminListViewModel $list_view_model,
		private ProjectSupportViewModel $detail_view_model,
		private TemplateLoader $templates
	) {}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
	}

	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Online Invitations', 'prikogstreg-online-invitations' ),
			__( 'Online Invitations', 'prikogstreg-online-invitations' ),
			Capabilities::SUPPORT,
			ProjectAdminListViewModel::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( Capabilities::SUPPORT ) ) {
			wp_die( esc_html__( 'You do not have permission to view invitation projects.', 'prikogstreg-online-invitations' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$project_id = isset( $_GET['project_id'] ) ? (int) $_GET['project_id'] : 0;
		if ( $project_id > 0 ) {
			$this->render_detail( $project_id );

			return;
		}

		$this->render_list();
	}

	private function render_list(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter = isset( $_GET['status'] ) ? (string) wp_unslash( $_GET['status'] ) : ProjectAdminFilter::ALL;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page   = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

		$context = $this->list_view_model->build_list( $filter, $page );

		echo '<div class="wrap pks-oi-admin-projects">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Online Invitations', 'prikogstreg-online-invitations' ) . '</h1>';
		echo '<hr class="wp-header-end" />';
		$this->templates->render( 'admin/projects-list', $context );
		echo '</div>';
	}

	private function render_detail( int $project_id ): void {
		$view = $this->detail_view_model->build( $project_id );
		if ( ! is_array( $view ) ) {
			echo '<div class="wrap pks-oi-admin-projects">';
			echo '<h1>' . esc_html__( 'Online Invitations', 'prikogstreg-online-invitations' ) . '</h1>';
			echo '<p>' . esc_html__( 'This invitation project could not be found.', 'prikogstreg-online-invitations' ) . '</p>';
			echo '<p><a class="button" href="' . esc_url( ProjectAdminListViewModel::list_url() ) . '">' . esc_html__( 'Back to all projects', 'prikogstreg-online-invitations' ) . '</a></p>';
			echo '</div>';

			return;
		}

		$view['back_url']  = ProjectAdminListViewModel::list_url();
		$view['read_only'] = true;

		echo '<div class="wrap pks-oi-admin-projects">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Invitation project details', 'prikogstreg-online-invitations' ) . '</h1>';
		echo ' <a href="' . esc_url( $view['back_url'] ) . '" class="page-title-action">' . esc_html__( 'Back to list', 'prikogstreg-online-invitations' ) . '</a>';
		echo '<hr class="wp-header-end" />';
		$this->templates->render( 'admin/project-detail', $view );
		echo '</div>';
	}
}
