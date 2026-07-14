<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin\Invitations;

use PrikOgStreg\OnlineInvitations\Admin\Capabilities;
use PrikOgStreg\OnlineInvitations\Admin\ProjectAdminListViewModel;
use PrikOgStreg\OnlineInvitations\Admin\ProjectSupportViewModel;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoModerationStatus;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoService;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;
use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;

/**
 * Canonical admin project detail screen with tabs.
 */
final class InvitationDetailPage {

	/** @var list<string> */
	private const TABS = [
		'overview',
		'invitation',
		'event',
		'guests',
		'wishlist',
		'photos',
		'delivery',
		'diagnostics',
		'tools',
	];

	public function __construct(
		private ProjectSupportViewModel $view_model,
		private GuestService $guests,
		private PhotoService $photos,
		private TemplateLoader $templates
	) {}

	public function render( int $project_id ): void {
		if ( ! current_user_can( Capabilities::VIEW ) ) {
			wp_die( esc_html__( 'You do not have permission to view this project.', 'prikogstreg-online-invitations' ) );
		}

		$view = $this->view_model->build_detail( $project_id );
		if ( ! is_array( $view ) ) {
			echo '<div class="wrap pks-oi-admin-projects">';
			echo '<h1>' . esc_html__( 'Invitation project', 'prikogstreg-online-invitations' ) . '</h1>';
			echo '<p>' . esc_html__( 'This invitation project could not be found.', 'prikogstreg-online-invitations' ) . '</p>';
			echo '<p><a class="button" href="' . esc_url( InvitationAdminQuery::list_url() ) . '">' . esc_html__( 'Back to all projects', 'prikogstreg-online-invitations' ) . '</a></p>';
			echo '</div>';

			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = sanitize_key( (string) ( $_GET['tab'] ?? 'overview' ) );
		if ( ! in_array( $tab, self::TABS, true ) ) {
			$tab = 'overview';
		}

		$view['tab']              = $tab;
		$view['tabs']             = $this->tab_links( $project_id, $view );
		$view['back_url']         = InvitationAdminQuery::list_url();
		$view['can_edit']         = current_user_can( Capabilities::EDIT );
		$view['can_moderate']     = current_user_can( Capabilities::MODERATE_PHOTOS );
		$view['can_tools']        = current_user_can( Capabilities::RUN_TOOLS ) || current_user_can( Capabilities::SUPPORT );
		$view['draft_preview_url']= InvitationPreviewController::preview_url( $project_id, 'draft' );
		$view['published_preview_url'] = InvitationPreviewController::preview_url( $project_id, 'published' );

		if ( 'guests' === $tab ) {
			$view['guest_list'] = $this->guests->list_for_project( $view['project'], 1, false );
		}

		if ( 'photos' === $tab && $view['can_moderate'] ) {
			$view['photo_list'] = $this->photos->list_for_owner( $view['project'], PhotoModerationStatus::PENDING );
		}

		echo '<div class="wrap pks-oi-admin-projects pks-oi-admin-support">';
		settings_errors( 'pks_oi_admin' );
		$this->templates->render( 'admin/invitation-detail', $view );
		echo '</div>';
	}

	/**
	 * @param array<string, mixed> $view
	 * @return array<string, array{label:string,url:string,active:bool}>
	 */
	private function tab_links( int $project_id, array $view ): array {
		$labels = [
			'overview'    => __( 'Overview', 'prikogstreg-online-invitations' ),
			'invitation'  => __( 'Invitation', 'prikogstreg-online-invitations' ),
			'event'       => __( 'Event details', 'prikogstreg-online-invitations' ),
			'guests'      => __( 'Guests & RSVP', 'prikogstreg-online-invitations' ),
			'wishlist'    => __( 'Wishlist', 'prikogstreg-online-invitations' ),
			'photos'      => __( 'Photos', 'prikogstreg-online-invitations' ),
			'delivery'    => __( 'Delivery', 'prikogstreg-online-invitations' ),
			'diagnostics' => __( 'Diagnostics', 'prikogstreg-online-invitations' ),
			'tools'       => __( 'Tools', 'prikogstreg-online-invitations' ),
		];

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = sanitize_key( (string) ( $_GET['tab'] ?? 'overview' ) );

		$tabs = [];
		foreach ( self::TABS as $tab ) {
			if ( 'tools' === $tab && ! current_user_can( Capabilities::RUN_TOOLS ) ) {
				continue;
			}
			if ( 'photos' === $tab && (int) ( $view['counts']['photos'] ?? 0 ) <= 0 ) {
				continue;
			}
			if ( 'wishlist' === $tab && (int) ( $view['counts']['wishlist'] ?? 0 ) <= 0 && empty( $view['project']['external_wishlist_url'] ) ) {
				continue;
			}

			$tabs[ $tab ] = [
				'label'  => $labels[ $tab ] ?? $tab,
				'url'    => ProjectAdminListViewModel::detail_url( $project_id, $tab ),
				'active' => $tab === $current,
			];
		}

		return $tabs;
	}
}
