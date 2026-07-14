<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\MyAccount;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Database\MigrationLock;
use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Domain\Project\DemoInvitationService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectEntitlement;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectEventService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectPreviewService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectPublishService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStateService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectArchiveService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectCustomerDeleteService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;
use PrikOgStreg\OnlineInvitations\Security\Authorization;
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
		private DemoInvitationService $demo_service,
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

		if ( 'publish' === $action && ProjectSections::PUBLISH === $route['section'] ) {
			$this->publish_service->publish( $project );
			wp_safe_redirect( add_query_arg( 'pks_oi_published', '1', $url ) );
			exit;
		}

		if ( 'unpublish' === $action && ProjectSections::PUBLISH === $route['section'] ) {
			$this->publish_service->unpublish( $project );
			wp_safe_redirect( add_query_arg( 'pks_oi_unpublished', '1', $url ) );
			exit;
		}

		if ( 'send_demo' === $action && ProjectSections::PUBLISH === $route['section'] ) {
			$this->demo_service->send_demo( $project, $this->authorization->current_user_id() );
			wp_safe_redirect( add_query_arg( 'pks_oi_demo', '1', $url ) );
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
				$this->templates->render( 'myaccount/project-publish', $context );
				return;
			case ProjectSections::GUESTS:
				$this->guest_controller->render( $project, $context );
				return;
			case ProjectSections::ADDRESS_BOOK:
				$this->address_book_controller->render( $project, $context );
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
			$context['editor_error'] = __( 'Design editing is not available for this project.', 'prikogstreg-online-invitations' );
			$this->templates->render( 'myaccount/project-design', $context );

			return;
		}

		$adapter = $this->builder->get_adapter();
		$state   = $this->state_service->load_canonical_state( $project );
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
		$preview = $this->preview_service->render_preview( $project );
		$context['preview_html']       = $preview['html'];
		$context['envelope_preset']    = $preview['envelope_preset'];
		$context['track_opens']        = false;
		$this->templates->render( 'myaccount/project-preview', $context );
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array<string, mixed>
	 */
	private function base_context( array $project, int $project_id, string $section ): array {
		return [
			'project'           => $project,
			'project_id'        => $project_id,
			'section'           => $section,
			'is_support'        => $this->authorization->is_support_view( $project ),
			'can_edit'          => $this->authorization->can_edit_project( $project ),
			'can_publish'       => ProjectEntitlement::can_publish_project( $project ),
			'sections'          => ProjectSections::labels(),
			'section_urls'      => $this->section_urls( $project_id ),
			'notices'           => $this->dependency_notices( $project ),
			'checklist'         => $this->build_checklist( $project ),
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
	private function build_checklist( array $project ): array {
		$project_id  = (int) ( $project['project_id'] ?? 0 );
		$has_design  = (int) ( $project['state_version'] ?? 0 ) >= 1 && '' === (string) ( $project['last_error_code'] ?? '' );
		$has_event   = ProjectEntitlement::has_required_event_data( $project );
		$published   = PublicationStatus::PUBLISHED === (string) ( $project['publication_status'] ?? '' );
		$guest_count = $this->guests->count_for_project( $project_id );
		$has_guests  = $guest_count > 0;

		return [
			'design'  => [
				'label'  => __( 'Design imported', 'prikogstreg-online-invitations' ),
				'done'   => $has_design,
				'detail' => $has_design
					? __( 'Your customised design is ready to edit.', 'prikogstreg-online-invitations' )
					: __( 'Import is pending or failed — contact support if this persists.', 'prikogstreg-online-invitations' ),
			],
			'event'   => [
				'label'  => __( 'Event details', 'prikogstreg-online-invitations' ),
				'done'   => $has_event,
				'detail' => $has_event
					? __( 'Event title and date are set.', 'prikogstreg-online-invitations' )
					: __( 'Add your event title and date before publishing.', 'prikogstreg-online-invitations' ),
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
			],
			'publish' => [
				'label'  => __( 'Published', 'prikogstreg-online-invitations' ),
				'done'   => $published,
				'detail' => $published
					? __( 'Your invitation is published.', 'prikogstreg-online-invitations' )
					: __( 'Publish when your project is ready to share.', 'prikogstreg-online-invitations' ),
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
				'label' => __( 'Continue setup', 'prikogstreg-online-invitations' ),
				'url'   => Endpoints::project_url( (int) ( $project['project_id'] ?? 0 ), ProjectSections::OVERVIEW ),
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
		}

		return $notices;
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
