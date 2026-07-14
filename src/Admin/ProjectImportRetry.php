<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin;

use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectService;

/**
 * Admin action to retry a failed project import.
 */
final class ProjectImportRetry {

	public function __construct(
		private ProjectService $projects
	) {}

	public function register(): void {
		add_action( 'admin_post_pks_oi_retry_project_import', [ $this, 'handle_retry' ] );
	}

	public function handle_retry(): void {
		if ( ! current_user_can( Capabilities::SUPPORT ) ) {
			wp_die( esc_html__( 'You do not have permission to retry project imports.', 'prikogstreg-online-invitations' ) );
		}

		$project_id = isset( $_GET['project_id'] ) ? (int) $_GET['project_id'] : 0;
		check_admin_referer( 'pks_oi_retry_project_import_' . $project_id );

		if ( $project_id <= 0 ) {
			wp_safe_redirect( wp_get_referer() ?: admin_url() );
			exit;
		}

		$success = $this->projects->retry_import( $project_id );

		if ( $success ) {
			add_settings_error(
				'pks_oi_admin',
				'pks_oi_retry_success',
				__( 'Project import retry completed successfully.', 'prikogstreg-online-invitations' ),
				'updated'
			);
		} else {
			add_settings_error(
				'pks_oi_admin',
				'pks_oi_retry_failed',
				__( 'Project import retry failed. Check the project error code and order payload.', 'prikogstreg-online-invitations' ),
				'error'
			);
		}

		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}

	public static function retry_url( int $project_id ): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=pks_oi_retry_project_import&project_id=' . $project_id ),
			'pks_oi_retry_project_import_' . $project_id
		);
	}
}
