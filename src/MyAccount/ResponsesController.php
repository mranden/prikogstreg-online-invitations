<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\MyAccount;

use PrikOgStreg\OnlineInvitations\Database\Repositories\EventRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestCsv;
use PrikOgStreg\OnlineInvitations\Domain\Guest\RsvpStatus;
use PrikOgStreg\OnlineInvitations\Security\Authorization;
use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;

/**
 * Owner response overview with filters, export, and recent change history.
 */
final class ResponsesController {

	public const PER_PAGE = 20;

	public function __construct(
		private GuestRepository $guests,
		private EventRepository $events,
		private Authorization $authorization,
		private TemplateLoader $templates
	) {}

	public function register(): void {
		add_action( 'template_redirect', [ $this, 'maybe_send_export' ], 4 );
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $context
	 */
	public function render( array $project, array $context ): void {
		$page         = isset( $_GET['pks_oi_response_page'] ) ? max( 1, (int) $_GET['pks_oi_response_page'] ) : 1;
		$status_filter = isset( $_GET['pks_oi_rsvp_status'] ) ? sanitize_key( (string) $_GET['pks_oi_rsvp_status'] ) : '';
		$valid_statuses = [ '', RsvpStatus::PENDING, RsvpStatus::ATTENDING, RsvpStatus::DECLINED ];
		if ( ! in_array( $status_filter, $valid_statuses, true ) ) {
			$status_filter = '';
		}

		$list = $this->guests->list_for_project_filtered(
			(int) $project['project_id'],
			$page,
			self::PER_PAGE,
			'' !== $status_filter ? $status_filter : null
		);

		$history = $this->events->list_rsvp_events_for_project( (int) $project['project_id'], 15 );

		$this->templates->render(
			'myaccount/project-responses',
			array_merge(
				$context,
				[
					'response_list'  => $list,
					'status_filter'  => $status_filter,
					'status_summary' => $this->guests->status_summary( (int) $project['project_id'] ),
					'recent_history' => $history,
				]
			)
		);
	}

	public function maybe_send_export(): void {
		if ( ! isset( $_GET['pks_oi_export_responses'], $_GET['pks_oi_project_id'] ) ) {
			return;
		}

		if ( ! is_user_logged_in() || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ), ProjectController::NONCE_ACTION ) ) {
			return;
		}

		$project = $this->authorization->resolve_viewable_project( (int) $_GET['pks_oi_project_id'] );
		if ( ! is_array( $project ) || ! $this->authorization->can_edit_project( $project ) ) {
			return;
		}

		$status_filter = isset( $_GET['pks_oi_rsvp_status'] ) ? sanitize_key( (string) $_GET['pks_oi_rsvp_status'] ) : '';
		$rows          = $this->guests->export_rows_for_project( (int) $project['project_id'] );

		if ( in_array( $status_filter, [ RsvpStatus::PENDING, RsvpStatus::ATTENDING, RsvpStatus::DECLINED ], true ) ) {
			$rows = array_values(
				array_filter(
					$rows,
					static fn( array $row ): bool => $status_filter === (string) ( $row['rsvp_status'] ?? '' )
				)
			);
		}

		$csv = GuestCsv::build_export( $rows );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="responses-' . (int) $project['project_id'] . '.csv"' );
		header( 'Cache-Control: no-store' );
		echo $csv;
		exit;
	}
}
