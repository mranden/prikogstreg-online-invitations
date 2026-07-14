<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\MyAccount;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Database\MigrationLock;
use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicEntitlement;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectDesignSource;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectEntitlement;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectEventService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectPreviewService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectPublicUrlService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectPublishService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStateService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectArchiveService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectCustomerDeleteService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;
use PrikOgStreg\OnlineInvitations\Security\Authorization;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageException;
use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;

/**
 * My Account project list, overview, and lifecycle sections.
 */
final class ProjectController {

	public const PER_PAGE = 10;

	public const NONCE_ACTION = 'pks_oi_myaccount_action';

	public function __construct(
		private ProjectRepository $projects,
		private GuestRepository $guests,
		private Authorization $authorization,
		private TemplateLoader $templates,
		private BuilderService $builder,
		private Router $router,
		private ProjectStateService $state_service,
		private ProjectEventService $event_service,
		private ProjectPreviewService $preview_service,
		private ProjectPublishService $publish_service,
		private ProjectPublicUrlService $public_url_service,
		private ProjectService $project_service,
		private GuestController $guest_controller,
		private AddressBookController $address_book_controller,
		private ResponsesController $responses_controller,
		private WishlistController $wishlist_controller,
		private PhotoController $photos_controller,
		private ProjectArchiveService $archive_service,
		private ProjectCustomerDeleteService $delete_service
	) {}

	public function register(): void {
		add_action( 'template_redirect', [ $this, 'handle_post_actions' ], 5 );
	}

	public function render_endpoint(): void {
		if ( ! is_user_logged_in() ) {
			$this->render_not_found();

			return;
		}

		$route = $this->router->parse_request();

		if ( 'list' === $route['mode'] ) {
			$this->render_list();

			return;
		}

		$this->render_project( $route['project_id'], $route['section'] );
	}

	public function handle_post_actions(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! self::verify_nonce() ) {
			return;
		}

		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}

		$route = $this->router->parse_request();
		if ( 'project' !== $route['mode'] ) {
			return;
		}

		$project = $this->authorization->resolve_viewable_project( $route['project_id'] );
		if ( ! is_array( $project ) || ! $this->authorization->can_edit_project( $project ) ) {
			return;
		}

		$action = sanitize_key( (string) ( $_POST['pks_oi_action'] ?? '' ) );
		$url    = Endpoints::project_url( $route['project_id'], $route['section'] );

		if ( $this->guest_controller->handle_post( $project, $route['section'], $url ) ) {
			return;
		}

		if ( $this->address_book_controller->handle_post( $project, $route['section'], $url ) ) {
			return;
		}

		if ( $this->wishlist_controller->handle_post( $project, $route['section'], $url ) ) {
			return;
		}

		if ( $this->photos_controller->handle_post( $project, $route['section'], $url ) ) {
			return;
		}

		if ( 'save_event' === $action && ProjectSections::EVENT === $route['section'] ) {
			$this->event_service->save_event_details( $project, wp_unslash( $_POST ) );
			wp_safe_redirect( add_query_arg( 'pks_oi_saved', '1', $url ) );
			exit;
		}

		if ( ProjectSections::SETTINGS !== $route['section'] ) {
			return;
		}

		if ( 'archive_project' === $action ) {
			if ( $this->archive_service->archive( $project, 'customer_settings' ) ) {
				wp_safe_redirect( add_query_arg( 'pks_oi_archived_project', '1', Endpoints::project_url( $route['project_id'], ProjectSections::SETTINGS ) ) );
				exit;
			}
		}

		if ( 'restore_project' === $action ) {
			if ( $this->archive_service->restore( $project, 'customer_settings' ) ) {
				wp_safe_redirect( add_query_arg( 'pks_oi_restored_project', '1', Endpoints::project_url( $route['project_id'], ProjectSections::SETTINGS ) ) );
				exit;
			}
		}

		if ( 'delete_project' === $action ) {
			$confirmation = sanitize_text_field( wp_unslash( (string) ( $_POST['pks_oi_delete_confirmation'] ?? '' ) ) );
			$result       = $this->delete_service->request_delete( $project, $this->authorization->current_user_id(), $confirmation );
			if ( $result['success'] ) {
				wp_safe_redirect( add_query_arg( 'pks_oi_deleted_project', '1', Endpoints::base_url() ) );
				exit;
			}
			wp_safe_redirect( add_query_arg( 'pks_oi_delete_error', rawurlencode( (string) ( $result['errors'][0] ?? 'failed' ) ), $url ) );
			exit;
		}
	}

	private function render_list(): void {
		$user_id = $this->authorization->current_user_id();
		$page    = isset( $_GET['pks_oi_page'] ) ? max( 1, (int) $_GET['pks_oi_page'] ) : 1;
		$result  = $this->projects->list_summary_for_user( $user_id, $page, self::PER_PAGE );

		$items = [];
		foreach ( $result['items'] as $row ) {
			$items[] = $this->format_list_item( $row );
		}

		$this->templates->render(
			'myaccount/project-list',
			[
				'items'      => $items,
				'pagination' => $result,
				'notices'    => $this->dependency_notices(),
				'list_url'   => Endpoints::base_url(),
			]
		);
	}

	private function render_project( int $project_id, string $section ): void {
		$project = $this->authorization->resolve_viewable_project( $project_id );
		if ( ! is_array( $project ) ) {
			$this->render_not_found();

			return;
		}

		$project = $this->project_service->recover_failed_import_if_needed( $project );

		$context = $this->base_context( $project, $project_id, $section );

		switch ( $section ) {
			case ProjectSections::OVERVIEW:
				$this->templates->render( 'myaccount/project-overview', $context );
				return;
			case ProjectSections::DESIGN:
				$this->render_design( $context, $project );
				return;
			case ProjectSections::EVENT:
				$this->templates->render( 'myaccount/project-event', $context );
				return;
			case ProjectSections::PREVIEW:
				$this->render_preview( $context, $project );
				return;
			case ProjectSections::PUBLISH:
				wp_safe_redirect( Endpoints::project_url( $project_id, ProjectSections::PREVIEW ) );
				exit;
			case ProjectSections::GUESTS:
				$this->guest_controller->render( $project, $context );
				return;
			case ProjectSections::RESPONSES:
				$this->responses_controller->render( $project, $context );
				return;
			case ProjectSections::WISHLIST:
				$this->wishlist_controller->render( $project, $context );
				return;
			case ProjectSections::PHOTOS:
				$this->photos_controller->render( $project, $context );
				return;
			case ProjectSections::SETTINGS:
				$context['is_archived'] = ProjectStatus::ARCHIVED === (string) ( $project['status'] ?? '' );
				$context['delete_confirmation_phrase'] = ProjectCustomerDeleteService::CONFIRMATION_PHRASE;
				$this->templates->render( 'myaccount/project-settings', $context );
				return;
			default:
				$context['implemented'] = false;
				$this->templates->render( 'myaccount/section-placeholder', $context );
		}
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $context
	 */
	private function render_design( array $context, array $project ): void {
		if ( ! $this->authorization->can_edit_project( $project ) ) {
			$context['editor_html'] = '';
			$context['editor_error'] = $this->design_unavailable_message( $project );
			$this->templates->render( 'myaccount/project-design', $context );

			return;
		}

		try {
			$state = $this->state_service->load_canonical_state( $project );
		} catch ( StorageException $exception ) {
			$context['editor_html']  = '';
			$context['editor_error'] = $this->design_unavailable_message( $project );
			$this->templates->render( 'myaccount/project-design', $context );

			return;
		}

		$adapter = $this->builder->get_adapter();
		$adapter_context = $this->state_service->adapter_context( $project, 'project_edit' );

		if ( null !== $adapter && method_exists( $adapter, 'enqueue_editor_assets' ) ) {
			$adapter->enqueue_editor_assets( $adapter_context );
		}

		$editor_html = '';
		if ( null !== $adapter && method_exists( $adapter, 'render_editor' ) ) {
			$rendered = $adapter->render_editor( $state, $adapter_context );
			if ( ! is_wp_error( $rendered ) ) {
				$editor_html = (string) $rendered;
			}
		}

		$context['editor_html']        = $editor_html;
		$context['state_version']    = (int) ( $project['state_version'] ?? 0 );
		$context['rest_save_url']      = rest_url( 'prikogstreg-online-invitations/v1/projects/' . (int) $project['project_id'] . '/state' );
		$context['rest_nonce']         = wp_create_nonce( 'wp_rest' );

		$this->templates->render( 'myaccount/project-design', $context );
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $context
	 */
	private function render_preview( array $context, array $project ): void {
		$project_id = (int) ( $project['project_id'] ?? 0 );

		if (
			$this->authorization->can_edit_project( $project )
			&& ProjectEntitlement::can_publish_project( $project )
			&& PublicationStatus::PUBLISHED !== (string) ( $project['publication_status'] ?? '' )
		) {
			$this->publish_service->publish( $project );
			$refreshed = $this->projects->find_by_id( $project_id );
			if ( is_array( $refreshed ) ) {
				$project = $refreshed;
			}
		}

		$public_url = '';
		if ( PublicEntitlement::is_publicly_available( $project ) ) {
			$public_url = $this->public_url_service->resolve_url( $project ) ?? '';
		}

		$preview = $this->preview_service->render_preview( $project );
		$project = $context['project'] ?? $project;
		$context['project']                 = $project;
		$context['preview_html']            = $preview['html'];
		$context['preview_uses_template_fallback'] = ProjectDesignSource::TEMPLATE_FALLBACK === (string) ( $project['design_source'] ?? '' );
		$context['envelope_preset']         = $preview['envelope_preset'];
		$context['track_opens']      = false;
		$context['public_url']       = $public_url;
		$context['is_public_live']   = PublicEntitlement::is_publicly_available( $project );
		$this->templates->render( 'myaccount/project-preview', $context );
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array<string, mixed>
	 */
	private function base_context( array $project, int $project_id, string $section ): array {
		$project['design_source'] = $this->state_service->read_design_source( $project );
		$guest_summary = $this->guests->status_summary( $project_id );
		$section_urls  = $this->section_urls( $project_id );

		return [
			'project'           => $project,
			'project_id'        => $project_id,
			'section'           => $section,
			'is_support'        => $this->authorization->is_support_view( $project ),
			'can_edit'          => $this->authorization->can_edit_project( $project ),
			'can_publish'       => ProjectEntitlement::can_publish_project( $project ),
			'sections'          => ProjectSections::visible_labels(),
			'section_urls'      => $section_urls,
			'notices'           => $this->dependency_notices( $project ),
			'checklist'         => $this->build_checklist( $project, $project_id ),
			'overview_stats'    => [
				[
					'label' => __( 'Guests', 'prikogstreg-online-invitations' ),
					'value' => (string) (int) ( $guest_summary['total'] ?? 0 ),
					'url'   => $section_urls[ ProjectSections::GUESTS ] ?? '',
				],
				[
					'label' => __( 'Attending', 'prikogstreg-online-invitations' ),
					'value' => (string) (int) ( $guest_summary['attending'] ?? 0 ),
					'url'   => $section_urls[ ProjectSections::RESPONSES ] ?? '',
				],
				[
					'label' => __( 'Opened', 'prikogstreg-online-invitations' ),
					'value' => (string) (int) ( $guest_summary['opened'] ?? 0 ),
					'url'   => $section_urls[ ProjectSections::RESPONSES ] ?? '',
				],
			],
			'next_action'       => $this->primary_next_action( $project ),
			'order_url'         => $this->order_admin_url( (int) ( $project['order_id'] ?? 0 ) ),
			'event_fields'      => ProjectEventService::ALLOWED_FIELDS,
		];
	}

	private function render_not_found(): void {
		$this->templates->render(
			'myaccount/not-found',
			[
				'message' => __( 'This invitation project could not be found.', 'prikogstreg-online-invitations' ),
			]
		);
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function format_list_item( array $row ): array {
		$project_id = (int) ( $row['project_id'] ?? 0 );

		return [
			'project_id'         => $project_id,
			'title'              => $this->project_title( $row ),
			'status'             => (string) ( $row['status'] ?? ProjectStatus::DRAFT ),
			'publication_status' => (string) ( $row['publication_status'] ?? PublicationStatus::UNPUBLISHED ),
			'event_date'         => (string) ( $row['event_start_utc'] ?? '' ),
			'updated_at'         => (string) ( $row['updated_at_utc'] ?? '' ),
			'next_action'        => $this->primary_next_action( $row ),
			'overview_url'       => Endpoints::project_url( $project_id ),
		];
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function project_title( array $project ): string {
		$title = trim( (string) ( $project['event_title'] ?? '' ) );
		if ( '' !== $title ) {
			return $title;
		}

		return sprintf(
			/* translators: %d: project ID */
			__( 'Invitation project #%d', 'prikogstreg-online-invitations' ),
			(int) ( $project['project_id'] ?? 0 )
		);
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array<string, array{label:string,done:bool,detail:string}>
	 */
	private function build_checklist( array $project, int $project_id ): array {
		$has_design  = (int) ( $project['state_version'] ?? 0 ) >= 1 && '' === (string) ( $project['last_error_code'] ?? '' );
		$uses_fallback = ProjectDesignSource::TEMPLATE_FALLBACK === (string) ( $project['design_source'] ?? '' );
		$has_event   = ProjectEntitlement::has_required_event_data( $project );
		$guest_count = $this->guests->count_for_project( $project_id );
		$has_guests  = $guest_count > 0;

		return [
			'design'  => [
				'label'  => $uses_fallback
					? __( 'Default template loaded', 'prikogstreg-online-invitations' )
					: __( 'Design imported', 'prikogstreg-online-invitations' ),
				'done'   => $has_design,
				'detail' => $has_design
					? ( $uses_fallback
						? __( 'No custom design was saved with your order. Customise the default template to get started.', 'prikogstreg-online-invitations' )
						: __( 'Your customised design is ready to edit.', 'prikogstreg-online-invitations' ) )
					: __( 'Import is pending or failed — contact support if this persists.', 'prikogstreg-online-invitations' ),
				'url'    => Endpoints::project_url( $project_id, ProjectSections::DESIGN ),
			],
			'event'   => [
				'label'  => __( 'Event details', 'prikogstreg-online-invitations' ),
				'done'   => $has_event,
				'detail' => $has_event
					? __( 'Event title and date are set.', 'prikogstreg-online-invitations' )
					: __( 'Add your event title and date before publishing.', 'prikogstreg-online-invitations' ),
				'url'    => Endpoints::project_url( $project_id, ProjectSections::EVENT ),
			],
			'guests'  => [
				'label'  => __( 'Guest list', 'prikogstreg-online-invitations' ),
				'done'   => $has_guests,
				'detail' => $has_guests
					? sprintf(
						/* translators: %d: guest count */
						_n( '%d guest added to this project.', '%d guests added to this project.', $guest_count, 'prikogstreg-online-invitations' ),
						$guest_count
					)
					: __( 'Add guests to send personal invitation links.', 'prikogstreg-online-invitations' ),
				'url'    => Endpoints::project_url( $project_id, ProjectSections::GUESTS ),
			],
		];
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function primary_next_action( array $project ): array {
		if ( '' !== (string) ( $project['last_error_code'] ?? '' ) ) {
			return [
				'label' => __( 'Import needs attention', 'prikogstreg-online-invitations' ),
				'url'   => Endpoints::project_url( (int) ( $project['project_id'] ?? 0 ) ),
			];
		}

		if ( (int) ( $project['state_version'] ?? 0 ) < 1 ) {
			return [
				'label' => __( 'Finish setup', 'prikogstreg-online-invitations' ),
				'url'   => Endpoints::project_url( (int) ( $project['project_id'] ?? 0 ) ),
			];
		}

		if ( PublicationStatus::PUBLISHED !== (string) ( $project['publication_status'] ?? '' ) ) {
			return [
				'label' => __( 'Preview invitation', 'prikogstreg-online-invitations' ),
				'url'   => Endpoints::project_url( (int) ( $project['project_id'] ?? 0 ), ProjectSections::PREVIEW ),
			];
		}

		return [
			'label' => __( 'View project', 'prikogstreg-online-invitations' ),
			'url'   => Endpoints::project_url( (int) ( $project['project_id'] ?? 0 ) ),
		];
	}

	/**
	 * @param array<string, mixed>|null $project
	 * @return list<array{type:string,message:string}>
	 */
	private function dependency_notices( ?array $project = null ): array {
		$notices = [];

		if ( MigrationLock::is_locked() ) {
			$notices[] = [
				'type'    => 'warning',
				'message' => __( 'A database update is in progress. Some invitation features may be temporarily unavailable.', 'prikogstreg-online-invitations' ),
			];
		}

		if ( ! $this->builder->is_available() ) {
			$notices[] = [
				'type'    => 'error',
				'message' => __( 'The PDF Builder integration is unavailable. Design editing is disabled until the adapter is active.', 'prikogstreg-online-invitations' ),
			];
		}

		if ( is_array( $project ) && '' !== (string) ( $project['last_error_code'] ?? '' ) ) {
			$notices[] = [
				'type'    => 'error',
				'message' => __( 'This project import failed and needs support attention before you can continue.', 'prikogstreg-online-invitations' ),
			];
		} elseif (
			is_array( $project )
			&& ProjectDesignSource::TEMPLATE_FALLBACK === (string) ( $project['design_source'] ?? '' )
		) {
			$notices[] = [
				'type'    => 'info',
				'message' => __( 'No custom design was saved with your order. We loaded the default template so you can customise it now.', 'prikogstreg-online-invitations' ),
			];
		}

		return $notices;
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function design_unavailable_message( array $project ): string {
		if ( '' !== (string) ( $project['last_error_code'] ?? '' ) ) {
			return __( 'Your order design could not be imported. Please contact support so we can restore your invitation.', 'prikogstreg-online-invitations' );
		}

		return __( 'Design editing is not available for this project.', 'prikogstreg-online-invitations' );
	}

	/**
	 * @return array<string, string>
	 */
	private function section_urls( int $project_id ): array {
		return Endpoints::section_urls( $project_id );
	}

	private function order_admin_url( int $order_id ): string {
		if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
			return '';
		}

		$order = wc_get_order( $order_id );
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_view_order_url' ) ) {
			return '';
		}

		return (string) $order->get_view_order_url();
	}

	public static function verify_nonce(): bool {
		return isset( $_POST['pks_oi_nonce'] )
			&& function_exists( 'wp_verify_nonce' )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['pks_oi_nonce'] ) ), self::NONCE_ACTION );
	}
}
