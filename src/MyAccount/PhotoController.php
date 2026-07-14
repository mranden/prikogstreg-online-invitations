<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\MyAccount;

use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoModerationStatus;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoService;
use PrikOgStreg\OnlineInvitations\Security\Authorization;
use PrikOgStreg\OnlineInvitations\Storage\FileStreamResponse;
use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;

/**
 * My Account photo moderation and authorized downloads.
 */
final class PhotoController {

	public function __construct(
		private PhotoService $photos,
		private Authorization $authorization,
		private TemplateLoader $templates,
		private FileStreamResponse $streams
	) {}

	public function register(): void {
		add_action( 'template_redirect', [ $this, 'maybe_stream_download' ], 6 );
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
		$this->templates->render(
			'myaccount/project-photos',
			array_merge(
				$context,
				[
					'photos'        => $this->photos->list_for_owner( $project, $status ),
					'status_filter' => $filter,
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

	public function maybe_stream_download(): void {
		if ( ! isset( $_GET['pks_oi_photo_download'] ) ) {
			return;
		}

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

		$row = $result['row'];
		try {
			$handle = $this->streams->open_relative(
				(string) ( $row['storage_uuid'] ?? '' ),
				(string) ( $row['relative_path'] ?? '' ),
				(string) ( $row['mime_type'] ?? 'application/octet-stream' ),
				(string) ( $row['sha256'] ?? '' )
			);
		} catch ( \Throwable ) {
			status_header( 404 );
			exit;
		}

		$filename = sanitize_file_name( (string) ( $row['original_filename'] ?? 'photo.jpg' ) );
		header( 'Content-Type: ' . $handle->mime_type );
		header( 'Content-Length: ' . (string) $handle->byte_size );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Cache-Control: private, no-store', true );

		if ( is_resource( $handle->stream ) ) {
			fpassthru( $handle->stream );
		}
		$handle->close();
		exit;
	}
}
