<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\MyAccount;

use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoModerationStatus;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoShareQrService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoShareSettingsService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoShareTokenService;
use PrikOgStreg\OnlineInvitations\Security\Authorization;
use PrikOgStreg\OnlineInvitations\Storage\FileStreamResponse;
use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;

/**
 * My Account photo settings, moderation, and authorized downloads.
 */
final class PhotoController {

	public function __construct(
		private PhotoService $photos,
		private PhotoShareSettingsService $settings,
		private PhotoShareTokenService $share_tokens,
		private PhotoShareQrService $qr,
		private GuestRepository $guests,
		private DeliveryQueueService $delivery_queue,
		private Authorization $authorization,
		private TemplateLoader $templates,
		private FileStreamResponse $streams
	) {}

	public function register(): void {
		add_action( 'template_redirect', [ $this, 'maybe_stream_asset' ], 6 );
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $context
	 */
	public function render( array $project, array $context ): void {
		$filter = isset( $_GET['pks_oi_photo_status'] )
			? sanitize_key( (string) $_GET['pks_oi_photo_status'] )
			: PhotoModerationStatus::PENDING;

		if ( ! in_array( $filter, [ PhotoModerationStatus::PENDING, PhotoModerationStatus::APPROVED, PhotoModerationStatus::REJECTED, 'all' ], true ) ) {
			$filter = PhotoModerationStatus::PENDING;
		}

		$status = 'all' === $filter ? null : $filter;
		$summary = $this->settings->summary( $project );
		$share_url = (string) ( $summary['share_url'] ?? '' );
		$guest_rows = $this->guests->list_for_project( (int) ( $project['project_id'] ?? 0 ), 1, 100 )['items'] ?? [];

		$this->templates->render(
			'myaccount/project-photos',
			array_merge(
				$context,
				[
					'photos'         => $this->photos->list_for_owner( $project, $status ),
					'status_filter'  => $filter,
					'photo_summary'  => $summary,
					'guests'         => $guest_rows,
					'preview_url'    => $share_url,
					'qr_url'         => $this->qr_download_url( (int) ( $project['project_id'] ?? 0 ) ),
					'qr_svg'         => '' !== $share_url ? $this->qr->svg_for_url( $share_url ) : '',
				]
			)
		);
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public function handle_post( array $project, string $section, string $redirect_url ): bool {
		if ( ProjectSections::PHOTOS !== $section ) {
			return false;
		}

		$action = sanitize_key( (string) ( $_POST['pks_oi_action'] ?? '' ) );

		if ( 'save_photo_settings' === $action ) {
			$result = $this->settings->save_settings( $project, wp_unslash( $_POST ) );
			$args   = [ 'pks_oi_saved' => ! empty( $result['success'] ) ? '1' : '0' ];
			if ( empty( $result['success'] ) && ! empty( $result['error'] ) ) {
				$args['pks_oi_error'] = sanitize_key( (string) $result['error'] );
			}
			wp_safe_redirect( add_query_arg( $args, $redirect_url ) );
			exit;
		}

		if ( 'rotate_photo_share_link' === $action ) {
			$this->settings->rotate_share_link( $project );
			wp_safe_redirect( add_query_arg( 'pks_oi_saved', '1', $redirect_url ) );
			exit;
		}

		if ( 'send_photo_share_invites' === $action ) {
			$this->queue_share_invites( $project, wp_unslash( $_POST ) );
			wp_safe_redirect( add_query_arg( 'pks_oi_saved', '1', $redirect_url ) );
			exit;
		}

		if ( 'bulk_photo_action' === $action ) {
			$bulk_action = sanitize_key( (string) ( $_POST['bulk_action'] ?? '' ) );
			$photo_ids   = array_map( 'intval', (array) ( $_POST['photo_ids'] ?? [] ) );
			$map         = [
				'approve' => 'approve',
				'reject'  => 'reject',
				'delete'  => 'delete',
			];
			if ( isset( $map[ $bulk_action ] ) && [] !== $photo_ids ) {
				$this->photos->moderate_bulk( $project, $photo_ids, $map[ $bulk_action ] );
			}
			wp_safe_redirect( add_query_arg( 'pks_oi_saved', '1', $redirect_url ) );
			exit;
		}

		$photo_id = (int) ( $_POST['photo_id'] ?? 0 );
		if ( in_array( $action, [ 'approve_photo', 'reject_photo', 'delete_photo' ], true ) && $photo_id > 0 ) {
			$map = [
				'approve_photo' => 'approve',
				'reject_photo'  => 'reject',
				'delete_photo'  => 'delete',
			];
			$this->photos->moderate( $project, $photo_id, $map[ $action ] );
			wp_safe_redirect( add_query_arg( 'pks_oi_saved', '1', $redirect_url ) );
			exit;
		}

		return false;
	}

	public function maybe_stream_asset(): void {
		if ( isset( $_GET['pks_oi_photo_download'] ) ) {
			$this->stream_download();
			return;
		}

		if ( isset( $_GET['pks_oi_photo_thumb'] ) ) {
			$this->stream_thumb();
			return;
		}

		if ( isset( $_GET['pks_oi_photo_qr'] ) ) {
			$this->stream_qr();
		}
	}

	private function stream_download(): void {
		if ( ! is_user_logged_in() || ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			status_header( 403 );
			exit;
		}

		$photo_id   = (int) $_GET['pks_oi_photo_download'];
		$project_id = isset( $_GET['pks_oi_project'] ) ? (int) $_GET['pks_oi_project'] : 0;
		$nonce      = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ) : '';

		if ( $photo_id <= 0 || $project_id <= 0 || ! wp_verify_nonce( $nonce, 'pks_oi_photo_download_' . $photo_id ) ) {
			status_header( 403 );
			exit;
		}

		$project = $this->authorization->resolve_viewable_project( $project_id );
		if ( ! is_array( $project ) ) {
			status_header( 404 );
			exit;
		}

		$result = $this->photos->resolve_download( $project, $photo_id, $this->authorization );
		if ( empty( $result['success'] ) || ! is_array( $result['row'] ?? null ) ) {
			status_header( 404 );
			exit;
		}

		$this->stream_row( $result['row'], false, true );
	}

	private function stream_thumb(): void {
		if ( ! is_user_logged_in() || ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			status_header( 403 );
			exit;
		}

		$photo_id   = (int) $_GET['pks_oi_photo_thumb'];
		$project_id = isset( $_GET['pks_oi_project'] ) ? (int) $_GET['pks_oi_project'] : 0;
		$nonce      = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ) : '';

		if ( $photo_id <= 0 || $project_id <= 0 || ! wp_verify_nonce( $nonce, 'pks_oi_photo_thumb_' . $photo_id ) ) {
			status_header( 403 );
			exit;
		}

		$project = $this->authorization->resolve_viewable_project( $project_id );
		if ( ! is_array( $project ) ) {
			status_header( 404 );
			exit;
		}

		$result = $this->photos->resolve_owner_stream( $project, $photo_id, $this->authorization, true );
		if ( empty( $result['success'] ) || ! is_array( $result['row'] ?? null ) ) {
			status_header( 404 );
			exit;
		}

		$this->stream_row( $result['row'], ! empty( $result['use_thumbnail'] ), false );
	}

	private function stream_qr(): void {
		if ( ! is_user_logged_in() || ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			status_header( 403 );
			exit;
		}

		$project_id = isset( $_GET['pks_oi_photo_qr'] ) ? (int) $_GET['pks_oi_photo_qr'] : 0;
		$nonce      = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ) : '';
		if ( $project_id <= 0 || ! wp_verify_nonce( $nonce, 'pks_oi_photo_qr_' . $project_id ) ) {
			status_header( 403 );
			exit;
		}

		$project = $this->authorization->resolve_viewable_project( $project_id );
		if ( ! is_array( $project ) ) {
			status_header( 404 );
			exit;
		}

		$raw = $this->share_tokens->resolve_raw_token( $project );
		if ( null === $raw ) {
			status_header( 404 );
			exit;
		}

		$svg = $this->qr->svg_for_url( PhotoShareTokenService::public_url( $raw ) );
		if ( '' === $svg ) {
			status_header( 404 );
			exit;
		}

		header( 'Content-Type: image/svg+xml; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="photo-share-qr.svg"' );
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Cache-Control: private, no-store', true );
		echo $svg;
		exit;
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function stream_row( array $row, bool $use_thumbnail, bool $attachment ): void {
		$relative = $use_thumbnail ? (string) ( $row['thumbnail_path'] ?? '' ) : (string) ( $row['relative_path'] ?? '' );
		if ( '' === $relative ) {
			$relative = (string) ( $row['relative_path'] ?? '' );
		}

		try {
			$handle = $this->streams->open_relative(
				(string) ( $row['storage_uuid'] ?? '' ),
				$relative,
				$use_thumbnail ? 'image/jpeg' : (string) ( $row['mime_type'] ?? 'application/octet-stream' ),
				$use_thumbnail ? '' : (string) ( $row['sha256'] ?? '' )
			);
		} catch ( \Throwable ) {
			status_header( 404 );
			exit;
		}

		header( 'Content-Type: ' . $handle->mime_type );
		header( 'Content-Length: ' . (string) $handle->byte_size );
		if ( $attachment ) {
			$filename = sanitize_file_name( (string) ( $row['original_filename'] ?? 'photo.jpg' ) );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		}
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Cache-Control: private, no-store', true );

		if ( is_resource( $handle->stream ) ) {
			fpassthru( $handle->stream );
		}
		$handle->close();
		exit;
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $input
	 */
	private function queue_share_invites( array $project, array $input ): void {
		$guest_ids = array_map( 'intval', (array) ( $input['guest_ids'] ?? [] ) );
		foreach ( $guest_ids as $guest_id ) {
			if ( $guest_id <= 0 ) {
				continue;
			}
			$guest = $this->guests->find_by_id_for_project( $guest_id, (int) ( $project['project_id'] ?? 0 ) );
			if ( is_array( $guest ) ) {
				$this->delivery_queue->queue_photo_share_invite( $project, $guest );
			}
		}
	}

	private function qr_download_url( int $project_id ): string {
		if ( $project_id <= 0 || ! function_exists( 'wc_get_account_endpoint_url' ) ) {
			return '';
		}

		return wp_nonce_url(
			add_query_arg( 'pks_oi_photo_qr', $project_id, wc_get_account_endpoint_url( 'online-invitations' ) ),
			'pks_oi_photo_qr_' . $project_id
		);
	}
}
