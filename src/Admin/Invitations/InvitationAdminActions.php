<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin\Invitations;

use PrikOgStreg\OnlineInvitations\Admin\AdminSupportService;
use PrikOgStreg\OnlineInvitations\Admin\Capabilities;
use PrikOgStreg\OnlineInvitations\Admin\ProjectAdminListViewModel;
use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectLifecycleAudit;

/**
 * POST handlers for safe administrator support edits.
 */
final class InvitationAdminActions {

	public const NONCE_ACTION = 'pks_oi_admin_edit';

	public function __construct(
		private ProjectRepository $projects,
		private GuestRepository $guests,
		private AdminSupportService $support,
		private PhotoService $photos,
		private ProjectLifecycleAudit $audit
	) {}

	public function register(): void {
		add_action( 'admin_post_pks_oi_admin_edit', [ $this, 'handle' ] );
	}

	public function handle(): void {
		$project_id = isset( $_POST['project_id'] ) ? (int) $_POST['project_id'] : 0;
		$action     = sanitize_key( (string) ( $_POST['pks_oi_admin_action'] ?? '' ) );

		check_admin_referer( self::NONCE_ACTION . '_' . $project_id );

		$project = $this->projects->find_by_id( $project_id );
		if ( ! is_array( $project ) ) {
			$this->redirect( $project_id, __( 'Project not found.', 'prikogstreg-online-invitations' ), 'error' );
		}

		$tab = sanitize_key( (string) ( $_POST['tab'] ?? 'overview' ) );

		$result = match ( $action ) {
			'save_event'      => $this->handle_save_event( $project, $tab ),
			'update_guest'    => $this->handle_update_guest( $project, $tab ),
			'moderate_photo'  => $this->handle_moderate_photo( $project, $tab ),
			default           => [ 'success' => false, 'message' => __( 'Unknown action.', 'prikogstreg-online-invitations' ) ],
		};

		$type = ( $result['success'] ?? false ) ? 'updated' : 'error';
		$this->redirect( $project_id, (string) ( $result['message'] ?? '' ), $type, $tab );
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array{success:bool,message:string}
	 */
	private function handle_save_event( array $project, string $tab ): array {
		if ( ! current_user_can( Capabilities::EDIT ) ) {
			return [ 'success' => false, 'message' => __( 'You do not have permission to edit event details.', 'prikogstreg-online-invitations' ) ];
		}

		$input = [];
		foreach ( array_keys( $_POST ) as $key ) {
			if ( str_starts_with( (string) $key, 'event_' ) || in_array( $key, [ 'venue_name', 'venue_address_line1', 'venue_address_line2', 'venue_city', 'venue_postcode', 'venue_country', 'practical_info', 'rsvp_deadline_utc', 'timezone', 'organiser_display_name', 'public_contact_email', 'public_contact_phone' ], true ) ) {
				$input[ $key ] = wp_unslash( $_POST[ $key ] );
			}
		}

		$result = $this->support->save_event_details( $project, $input );

		return [
			'success' => (bool) ( $result['success'] ?? false ),
			'message' => ( $result['success'] ?? false )
				? __( 'Event details saved.', 'prikogstreg-online-invitations' )
				: __( 'Event details could not be saved.', 'prikogstreg-online-invitations' ),
		];
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array{success:bool,message:string}
	 */
	private function handle_update_guest( array $project, string $tab ): array {
		if ( ! current_user_can( Capabilities::EDIT ) ) {
			return [ 'success' => false, 'message' => __( 'You do not have permission to edit guests.', 'prikogstreg-online-invitations' ) ];
		}

		$guest_id = (int) ( $_POST['guest_id'] ?? 0 );
		$guest    = $this->guests->find_by_id_for_project( $guest_id, (int) $project['project_id'] );
		if ( ! is_array( $guest ) ) {
			return [ 'success' => false, 'message' => __( 'Guest not found.', 'prikogstreg-online-invitations' ) ];
		}

		$input = [
			'display_name'   => wp_unslash( (string) ( $_POST['display_name'] ?? '' ) ),
			'email'          => wp_unslash( (string) ( $_POST['email'] ?? '' ) ),
			'rsvp_status'    => wp_unslash( (string) ( $_POST['rsvp_status'] ?? '' ) ),
			'attendee_count' => wp_unslash( (string) ( $_POST['attendee_count'] ?? '' ) ),
		];

		$result = $this->support->update_guest( $project, $guest, $input );

		return [
			'success' => (bool) ( $result['success'] ?? false ),
			'message' => ( $result['success'] ?? false )
				? __( 'Guest updated.', 'prikogstreg-online-invitations' )
				: __( 'Guest could not be updated.', 'prikogstreg-online-invitations' ),
		];
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array{success:bool,message:string}
	 */
	private function handle_moderate_photo( array $project, string $tab ): array {
		if ( ! current_user_can( Capabilities::MODERATE_PHOTOS ) ) {
			return [ 'success' => false, 'message' => __( 'You do not have permission to moderate photos.', 'prikogstreg-online-invitations' ) ];
		}

		$photo_id = (int) ( $_POST['photo_id'] ?? 0 );
		$action   = sanitize_key( (string) ( $_POST['photo_action'] ?? '' ) );
		$map      = [
			'approve' => 'approve',
			'reject'  => 'reject',
			'delete'  => 'delete',
		];

		if ( ! isset( $map[ $action ] ) ) {
			return [ 'success' => false, 'message' => __( 'Invalid photo action.', 'prikogstreg-online-invitations' ) ];
		}

		$result = $this->photos->moderate( $project, $photo_id, $map[ $action ] );
		if ( $result['success'] ?? false ) {
			$this->audit->record_admin(
				(int) $project['project_id'],
				'admin.photo_moderated',
				[
					'photo_id' => $photo_id,
					'action'   => $action,
				]
			);
		}

		return [
			'success' => (bool) ( $result['success'] ?? false ),
			'message' => ( $result['success'] ?? false )
				? __( 'Photo moderation updated.', 'prikogstreg-online-invitations' )
				: __( 'Photo moderation failed.', 'prikogstreg-online-invitations' ),
		];
	}

	private function redirect( int $project_id, string $message, string $type, string $tab = 'overview' ): void {
		add_settings_error( 'pks_oi_admin', 'pks_oi_admin_edit', $message, $type );
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		$url = $project_id > 0
			? ProjectAdminListViewModel::detail_url( $project_id, $tab )
			: InvitationAdminQuery::list_url();

		wp_safe_redirect( $url );
		exit;
	}
}
