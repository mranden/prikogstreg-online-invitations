<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin;

use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Domain\Project\GenericTokenService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectExpiration;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectHardDeleteService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectPublishService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectRestoreService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectRestrictionService;
use PrikOgStreg\OnlineInvitations\Scheduling\WelcomeScheduler;

/**
 * Admin-post handlers for support lifecycle actions.
 */
final class ProjectSupportActions {

	public const NONCE_ACTION = 'pks_oi_support_action';

	public function __construct(
		private ProjectRepository $projects,
		private ProjectRestrictionService $restriction,
		private ProjectRestoreService $restore,
		private ProjectPublishService $publish,
		private GenericTokenService $generic_tokens,
		private WelcomeScheduler $welcome,
		private ProjectHardDeleteService $hard_delete
	) {}

	public function register(): void {
		add_action( 'admin_post_pks_oi_support_action', [ $this, 'handle' ] );
	}

	public function handle(): void {
		if ( ! current_user_can( Capabilities::SUPPORT ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'prikogstreg-online-invitations' ) );
		}

		$project_id = isset( $_POST['project_id'] ) ? (int) $_POST['project_id'] : 0;
		$action     = sanitize_key( (string) ( $_POST['pks_oi_support_action'] ?? '' ) );
		$reason     = sanitize_text_field( wp_unslash( (string) ( $_POST['pks_oi_reason'] ?? '' ) ) );

		check_admin_referer( self::NONCE_ACTION . '_' . $project_id );

		$project = $this->projects->find_by_id( $project_id );
		if ( ! is_array( $project ) ) {
			$this->redirect_with_notice( $project_id, __( 'Project not found.', 'prikogstreg-online-invitations' ), 'error' );
		}

		$ok = match ( $action ) {
			'restrict'              => $this->restriction->restrict( $project, $reason, 'admin' ),
			'restore'               => ( $this->restore->restore( $project, $reason )['success'] ?? false ),
			'publish'               => ( $this->publish->publish( $project )['success'] ?? false ),
			'unpublish'             => ( $this->publish->unpublish( $project )['success'] ?? false ),
			'set_expiry_override'   => $this->set_expiry_override( $project, (string) ( $_POST['expiry_override_utc'] ?? '' ) ),
			'clear_expiry_override' => $this->clear_expiry_override( $project ),
			'rotate_generic_token'  => $this->rotate_generic_token( $project ),
			'resend_welcome'        => $this->welcome->resend_admin( $project_id ),
			'hard_delete'           => ( $this->hard_delete->delete( $project, $reason ) )->success,
			default                 => false,
		};

		if ( $ok ) {
			$this->redirect_with_notice( $project_id, __( 'Support action completed.', 'prikogstreg-online-invitations' ), 'updated' );
		}

		$this->redirect_with_notice( $project_id, __( 'Support action could not be completed.', 'prikogstreg-online-invitations' ), 'error' );
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function set_expiry_override( array $project, string $value ): bool {
		$project_id = (int) ( $project['project_id'] ?? 0 );
		$value      = trim( $value );
		$override   = null;
		if ( '' !== $value ) {
			$timestamp = strtotime( $value . ' UTC' );
			if ( false === $timestamp ) {
				return false;
			}
			$override = gmdate( 'Y-m-d H:i:s', $timestamp );
		}

		$this->projects->update(
			$project_id,
			[
				'expiry_override_utc' => $override,
				'expires_at_utc'      => $override ?? ProjectExpiration::recalculate_stored_expiry( $project ),
			]
		);
		do_action( 'pks_oi_project_expiry_changed', $project_id );

		return true;
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function clear_expiry_override( array $project ): bool {
		$project_id = (int) ( $project['project_id'] ?? 0 );
		$merged     = $project;
		$merged['expiry_override_utc'] = null;

		$this->projects->update(
			$project_id,
			[
				'expiry_override_utc' => null,
				'expires_at_utc'      => ProjectExpiration::recalculate_stored_expiry( $merged ),
			]
		);
		do_action( 'pks_oi_project_expiry_changed', $project_id );

		return true;
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function rotate_generic_token( array $project ): bool {
		$result = $this->generic_tokens->rotate( $project );

		return '' !== (string) ( $result['url'] ?? '' );
	}

	private function redirect_with_notice( int $project_id, string $message, string $type ): void {
		add_settings_error( 'pks_oi_admin', 'pks_oi_support', $message, $type );
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		$url = $project_id > 0
			? ProjectAdminListViewModel::detail_url( $project_id, 'tools' )
			: wp_get_referer();

		wp_safe_redirect( is_string( $url ) && '' !== $url ? $url : admin_url() );
		exit;
	}
}
