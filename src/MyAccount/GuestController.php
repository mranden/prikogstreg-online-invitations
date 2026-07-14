<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\MyAccount;

use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Domain\AddressBook\AddressBookService;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\InvitationSendService;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestCsv;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestImportService;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestLinkFlash;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestService;
use PrikOgStreg\OnlineInvitations\Security\Authorization;
use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;

/**
 * My Account guest list, forms, CSV, and bulk actions.
 */
final class GuestController {

	public function __construct(
		private GuestService $guests,
		private GuestImportService $import,
		private GuestRepository $guest_repository,
		private AddressBookService $address_book,
		private Authorization $authorization,
		private TemplateLoader $templates,
		private InvitationSendService $invitation_send
	) {}

	public function register(): void {
		add_action( 'template_redirect', [ $this, 'maybe_send_export' ], 4 );
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $context
	 */
	public function render( array $project, array $context ): void {
		$page              = isset( $_GET['pks_oi_guest_page'] ) ? max( 1, (int) $_GET['pks_oi_guest_page'] ) : 1;
		$include_archived  = isset( $_GET['pks_oi_show_archived'] );
		$list              = $this->guests->list_for_project( $project, $page, $include_archived );
		$edit_guest        = null;
		$edit_id           = isset( $_GET['pks_oi_edit_guest'] ) ? (int) $_GET['pks_oi_edit_guest'] : 0;

		if ( $edit_id > 0 ) {
			$edit_guest = $this->guest_repository->find_by_id_for_project( $edit_id, (int) $project['project_id'] );
		}

		$flashed_link = '';
		if ( isset( $_GET['pks_oi_guest_id'] ) ) {
			$flashed_link = GuestLinkFlash::consume( (int) $_GET['pks_oi_guest_id'], $this->authorization->current_user_id() );
		}

		$this->templates->render(
			'myaccount/project-guests',
			array_merge(
				$context,
				[
					'guest_list'      => $list,
					'edit_guest'      => $edit_guest,
					'include_archived'=> $include_archived,
					'flashed_link'    => $flashed_link,
					'import_preview'  => $this->load_import_preview( (int) $project['project_id'] ),
				]
			)
		);
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public function handle_post( array $project, string $section, string $redirect_url ): bool {
		if ( ProjectSections::GUESTS !== $section ) {
			return false;
		}

		$action = sanitize_key( (string) ( $_POST['pks_oi_action'] ?? '' ) );

		if ( 'save_guest' === $action ) {
			$guest_id = (int) ( $_POST['guest_id'] ?? 0 );
			if ( $guest_id > 0 ) {
				$guest  = $this->guest_repository->find_by_id_for_project( $guest_id, (int) $project['project_id'] );
				$result = is_array( $guest )
					? $this->guests->update( $project, $guest, wp_unslash( $_POST ) )
					: [ 'success' => false ];
			} else {
				$result = $this->guests->create( $project, wp_unslash( $_POST ) );
				if ( ! empty( $result['success'] ) && ! empty( $result['invitation_url'] ) && ! empty( $result['guest_id'] ) ) {
					GuestLinkFlash::store(
						(int) $result['guest_id'],
						$this->authorization->current_user_id(),
						(string) $result['invitation_url']
					);
					$redirect_url = add_query_arg( 'pks_oi_guest_id', (int) $result['guest_id'], $redirect_url );
				}
			}

			wp_safe_redirect( add_query_arg( 'pks_oi_saved', empty( $result['success'] ) ? '0' : '1', $redirect_url ) );
			exit;
		}

		if ( 'send_invitations' === $action ) {
			$ids    = array_map( 'intval', (array) ( $_POST['guest_ids'] ?? [] ) );
			$resend = ! empty( $_POST['pks_oi_resend'] );
			$result = $this->invitation_send->send_to_guests( $project, $ids, $resend );
			wp_safe_redirect(
				add_query_arg(
					[
						'pks_oi_sent'    => (int) ( $result['queued'] ?? 0 ),
						'pks_oi_skipped' => (int) ( $result['skipped'] ?? 0 ),
					],
					$redirect_url
				)
			);
			exit;
		}

		if ( 'archive_guests' === $action ) {
			$ids = array_map( 'intval', (array) ( $_POST['guest_ids'] ?? [] ) );
			$this->guests->archive_many( $project, $ids );
			wp_safe_redirect( add_query_arg( 'pks_oi_archived', count( $ids ), $redirect_url ) );
			exit;
		}

		if ( 'restore_guest' === $action ) {
			$guest = $this->guest_repository->find_by_id_for_project( (int) ( $_POST['guest_id'] ?? 0 ), (int) $project['project_id'] );
			if ( is_array( $guest ) ) {
				$result = $this->guests->restore( $project, $guest );
				if ( ! empty( $result['invitation_url'] ) ) {
					GuestLinkFlash::store( (int) $guest['guest_id'], $this->authorization->current_user_id(), (string) $result['invitation_url'] );
					$redirect_url = add_query_arg( 'pks_oi_guest_id', (int) $guest['guest_id'], $redirect_url );
				}
			}
			wp_safe_redirect( add_query_arg( 'pks_oi_restored', '1', $redirect_url ) );
			exit;
		}

		if ( 'regenerate_link' === $action ) {
			$guest = $this->guest_repository->find_by_id_for_project( (int) ( $_POST['guest_id'] ?? 0 ), (int) $project['project_id'] );
			if ( is_array( $guest ) ) {
				$result = $this->guests->regenerate_link( $project, $guest );
				if ( ! empty( $result['invitation_url'] ) ) {
					GuestLinkFlash::store( (int) $guest['guest_id'], $this->authorization->current_user_id(), (string) $result['invitation_url'] );
					$redirect_url = add_query_arg( 'pks_oi_guest_id', (int) $guest['guest_id'], $redirect_url );
				}
			}
			wp_safe_redirect( add_query_arg( 'pks_oi_link', '1', $redirect_url ) );
			exit;
		}

		if ( 'save_guest_to_address_book' === $action ) {
			$guest = $this->guest_repository->find_by_id_for_project( (int) ( $_POST['guest_id'] ?? 0 ), (int) $project['project_id'] );
			if ( is_array( $guest ) ) {
				$this->address_book->save_guest_snapshot( (int) ( $project['user_id'] ?? 0 ), $guest );
			}
			wp_safe_redirect( add_query_arg( 'pks_oi_saved_ab', '1', $redirect_url ) );
			exit;
		}

		if ( 'import_preview' === $action && ! empty( $_FILES['guest_csv']['tmp_name'] ) ) {
			$csv = (string) file_get_contents( (string) $_FILES['guest_csv']['tmp_name'] );
			$result = $this->import->preview( $project, $csv );
			if ( ! empty( $result['success'] ) ) {
				$this->store_import_preview( (int) $project['project_id'], $result['preview'] );
			}
			wp_safe_redirect( add_query_arg( 'pks_oi_import_preview', empty( $result['success'] ) ? '0' : '1', $redirect_url ) );
			exit;
		}

		if ( 'import_confirm' === $action ) {
			$preview = $this->load_import_preview( (int) $project['project_id'] );
			$rows    = [];
			foreach ( $preview['rows'] ?? [] as $entry ) {
				if ( is_array( $entry['row'] ?? null ) ) {
					$rows[] = $entry['row'];
				}
			}
			$report = $this->import->import_rows( $project, $rows );
			$this->clear_import_preview( (int) $project['project_id'] );
			set_transient( $this->import_report_key( (int) $project['project_id'] ), $report, 300 );
			wp_safe_redirect( add_query_arg( 'pks_oi_import_done', '1', $redirect_url ) );
			exit;
		}

		return false;
	}

	public function maybe_send_export(): void {
		if ( ! isset( $_GET['pks_oi_export_guests'], $_GET['pks_oi_project_id'] ) ) {
			return;
		}

		if ( ! is_user_logged_in() || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ), ProjectController::NONCE_ACTION ) ) {
			return;
		}

		$project = $this->authorization->resolve_viewable_project( (int) $_GET['pks_oi_project_id'] );
		if ( ! is_array( $project ) || ! $this->authorization->can_edit_project( $project ) ) {
			return;
		}

		$rows = $this->guest_repository->export_rows_for_project( (int) $project['project_id'] );
		$csv  = GuestCsv::build_export( $rows );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="guests-' . (int) $project['project_id'] . '.csv"' );
		header( 'Cache-Control: no-store' );
		echo $csv;
		exit;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function load_import_preview( int $project_id ): array {
		$preview = get_transient( $this->import_preview_key( $project_id ) );

		return is_array( $preview ) ? $preview : [];
	}

	/**
	 * @param array<string, mixed> $preview
	 */
	private function store_import_preview( int $project_id, array $preview ): void {
		set_transient( $this->import_preview_key( $project_id ), $preview, 900 );
	}

	private function clear_import_preview( int $project_id ): void {
		delete_transient( $this->import_preview_key( $project_id ) );
	}

	private function import_preview_key( int $project_id ): string {
		return 'pks_oi_import_preview_' . $project_id . '_' . $this->authorization->current_user_id();
	}

	private function import_report_key( int $project_id ): string {
		return 'pks_oi_import_report_' . $project_id . '_' . $this->authorization->current_user_id();
	}
}
