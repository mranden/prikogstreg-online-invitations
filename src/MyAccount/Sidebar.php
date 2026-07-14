<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\MyAccount;

use PrikOgStreg\OnlineInvitations\Security\Authorization;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStateService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectService;
use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;

/**
 * Opts into the Prikogstreg My Account sidebar and renders project section navigation.
 */
final class Sidebar {

	public function __construct(
		private Authorization $authorization,
		private Router $router,
		private TemplateLoader $templates,
		private SectionNavBuilder $section_nav,
		private ?ProjectService $project_service = null,
		private ?ProjectStateService $state_service = null
	) {}

	public function register(): void {
		add_action( 'wp', [ $this, 'prepare_context' ], 5 );
		add_filter( 'prikogstreg_my_account_show_sidebar', [ $this, 'show_sidebar' ], 10, 2 );
		add_action( 'prikogstreg_my_account_sidebar', [ $this, 'render_sidebar' ] );
	}

	public function prepare_context(): void {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() || ! is_user_logged_in() ) {
			return;
		}

		if ( ! function_exists( 'prikogstreg_my_account' ) || ! prikogstreg_my_account()->is_online_invitations_context() ) {
			return;
		}

		$route = $this->router->parse_request();
		if ( 'project' !== $route['mode'] ) {
			return;
		}

		$project = $this->authorization->resolve_viewable_project( $route['project_id'] );
		if ( ! is_array( $project ) ) {
			return;
		}

		if ( null !== $this->project_service ) {
			$project = $this->project_service->recover_failed_import_if_needed( $project );
		}

		if ( null !== $this->state_service ) {
			$project['design_source'] = $this->state_service->read_design_source( $project );
		}

		$user_id = $this->authorization->current_user_id();
		$nav     = $this->section_nav->build( $project, $route['section'], $user_id );

		SidebarContext::set(
			array_merge(
				$nav,
				[
					'project_id'    => $route['project_id'],
					'project_title' => $this->project_title( $project ),
					'list_url'      => Endpoints::base_url(),
					'is_support'    => $this->authorization->is_support_view( $project ),
				]
			)
		);
	}

	public function show_sidebar( bool $show, string $endpoint ): bool {
		if ( ! SidebarContext::has_nav() ) {
			return $show;
		}

		if ( Endpoints::SLUG === $endpoint ) {
			return true;
		}

		if ( function_exists( 'prikogstreg_my_account' ) && prikogstreg_my_account()->is_online_invitations_context() ) {
			return true;
		}

		return $show;
	}

	public function render_sidebar(): void {
		if ( ! SidebarContext::has_nav() ) {
			return;
		}

		if ( ! function_exists( 'prikogstreg_my_account' ) || ! prikogstreg_my_account()->is_online_invitations_context() ) {
			return;
		}

		$this->templates->render( 'myaccount/sidebar-nav', SidebarContext::get() );
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
}
