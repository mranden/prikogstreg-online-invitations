<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\MyAccount;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\AddressBook\AddressBookService;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\InvitationSendService;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestImportService;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestService;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestTokenService;
use PrikOgStreg\OnlineInvitations\Domain\Project\GenericTokenService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectArchiveService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectCustomerDeleteService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectHardDeleteService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectLifecycleAudit;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectEventService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectEntitlement;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectPreviewService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectPublicUrlService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectPublishService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectFactory;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoAccessCodeService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoAccessRateLimiter;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoServiceFactory;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoShareQrService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoShareSettingsService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoShareTokenService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStateService;
use PrikOgStreg\OnlineInvitations\Domain\Wishlist\WishlistItemService;
use PrikOgStreg\OnlineInvitations\Public\PosterDisplayAssets;
use PrikOgStreg\OnlineInvitations\Scheduling\WelcomeScheduler;
use PrikOgStreg\OnlineInvitations\Security\Authorization;
use PrikOgStreg\OnlineInvitations\Storage\StorageRegistry;
use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;

/**
 * Registers My Account routes, assets, and rendering.
 */
final class MyAccountRegistrar {

	private ProjectController $controller;

	private AccountPresentation $presentation;

	private Authorization $authorization;

	private TemplateLoader $templates;

	private SectionNavBuilder $section_nav;

	private ProjectService $project_service;

	private ProjectStateService $state_service;

	private PosterDisplayAssets $poster_assets;

	private Router $router;

	private Sidebar $sidebar;

	public function __construct(
		RepositoryRegistry $repositories,
		BuilderService $builder,
		StorageRegistry $storage,
		TemplateLoader $templates
	) {
		$this->templates     = $templates;
		$this->authorization = new Authorization( $repositories->projects() );
		$this->section_nav   = new SectionNavBuilder(
			$repositories->guests(),
			$repositories->wishlist_items(),
			$repositories->photos(),
			$repositories->address_book()
		);
		$authorization       = $this->authorization;
		$state_service = new ProjectStateService(
			$builder,
			$storage->project_storage(),
			$repositories->projects(),
			$repositories->events()
		);
		$guest_tokens = new GuestTokenService( $repositories->guests() );
		$guest_service = new GuestService( $repositories->guests(), $guest_tokens );
		$address_book_service = new AddressBookService(
			$repositories->address_book(),
			$repositories->guests(),
			$guest_service,
			$repositories->events()
		);
		$queue = new DeliveryQueueService( $repositories->deliveries() );
		$guest_controller = new GuestController(
			$guest_service,
			new GuestImportService( $repositories->guests(), $guest_service, $address_book_service ),
			$repositories->guests(),
			$address_book_service,
			$authorization,
			$templates,
			new InvitationSendService( $repositories->guests(), $queue )
		);
		$responses_controller = new ResponsesController(
			$repositories->guests(),
			$repositories->events(),
			$authorization,
			$templates
		);
		$wishlist_controller = new WishlistController(
			new WishlistItemService(
				$repositories->wishlist_items(),
				$repositories->wishlist_reservations(),
				$repositories->projects(),
				$repositories->guests(),
				$repositories->events()
			),
			$authorization,
			$templates
		);
		$queue = new DeliveryQueueService( $repositories->deliveries() );
		$photo_service = PhotoServiceFactory::create( $repositories, $storage );
		$share_tokens  = new PhotoShareTokenService( $repositories->projects() );
		$photos_controller = new PhotoController(
			$photo_service,
			new PhotoShareSettingsService(
				$repositories->projects(),
				$repositories->photos(),
				$share_tokens,
				new PhotoAccessCodeService( $repositories->projects(), new PhotoAccessRateLimiter() )
			),
			$share_tokens,
			new PhotoShareQrService(),
			$repositories->guests(),
			$queue,
			$authorization,
			$templates,
			$storage->file_streams()
		);
		$audit = new ProjectLifecycleAudit( $repositories->events() );
		$archive_service = new ProjectArchiveService(
			$repositories->projects(),
			$queue,
			$audit
		);
		$delete_service = new ProjectCustomerDeleteService(
			$repositories->projects(),
			new ProjectHardDeleteService(
				$repositories->projects(),
				$queue,
				$audit,
				$storage->project_storage()
			)
		);
		$project_service = new ProjectService(
			$repositories->projects(),
			$repositories->events(),
			new ProjectFactory(),
			$builder,
			$storage->project_storage(),
			new WelcomeScheduler(
				$repositories->projects(),
				$repositories->deliveries(),
				$queue
			)
		);
		$this->project_service = $project_service;
		$this->state_service   = $state_service;
		$this->poster_assets   = new PosterDisplayAssets( $storage->project_storage() );
		$this->router          = new Router();

		$this->controller = new ProjectController(
			$repositories->projects(),
			$repositories->guests(),
			$authorization,
			$templates,
			$builder,
			new Router(),
			$state_service,
			new ProjectEventService( $repositories->projects(), $repositories->events() ),
			new ProjectPreviewService( $builder, $state_service ),
			$this->poster_assets,
			new ProjectPublishService(
				$builder,
				$storage->project_storage(),
				$repositories->projects(),
				$state_service,
				$repositories->events()
			),
			new ProjectPublicUrlService( new GenericTokenService( $repositories->projects() ) ),
			$project_service,
			$guest_controller,
			new AddressBookController(
				$address_book_service,
				$repositories->address_book(),
				$authorization,
				$templates
			),
			$responses_controller,
			$wishlist_controller,
			$photos_controller,
			$archive_service,
			$delete_service
		);

		$this->presentation = new AccountPresentation( $repositories->projects() );

		$this->sidebar = new Sidebar(
			$this->authorization,
			new Router(),
			$this->templates,
			$this->section_nav,
			$this->project_service,
			$this->state_service
		);

		$guest_controller->register();
		$responses_controller->register();
		$photos_controller->register();
	}

	public function register(): void {
		( new Endpoints() )->register();
		$this->presentation->register();
		$this->sidebar->register();
		$this->controller->register();
		add_action( 'woocommerce_account_' . Endpoints::SLUG . '_endpoint', [ $this->controller, 'render_endpoint' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function enqueue_assets(): void {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}

		wp_enqueue_style(
			'pks-oi-account',
			PKS_OI_PLUGIN_URL . 'assets/build/css/account.css',
			[],
			PKS_OI_VERSION
		);

		wp_enqueue_script(
			'pks-oi-account',
			PKS_OI_PLUGIN_URL . 'assets/build/js/account.js',
			[],
			PKS_OI_VERSION,
			true
		);

		wp_localize_script(
			'pks-oi-account',
			'pksOiAccount',
			[
				'i18n' => [
					'saving'           => __( 'Saving…', 'prikogstreg-online-invitations' ),
					'saved'            => __( 'Saved.', 'prikogstreg-online-invitations' ),
					'save_failed'      => __( 'Save failed. Check your connection and try again.', 'prikogstreg-online-invitations' ),
					'save_conflict'    => __( 'Your design was changed elsewhere. Reload the page and try again.', 'prikogstreg-online-invitations' ),
					'save_unavailable' => __( 'Save endpoint unavailable.', 'prikogstreg-online-invitations' ),
					'invalid_payload'  => __( 'Invalid save payload from editor.', 'prikogstreg-online-invitations' ),
					'copied'           => __( 'Link copied.', 'prikogstreg-online-invitations' ),
				],
			]
		);

		$this->maybe_enqueue_poster_preview_assets();
	}

	private function maybe_enqueue_poster_preview_assets(): void {
		$route = $this->router->parse_request();
		if ( 'project' !== $route['mode'] ) {
			return;
		}

		if ( ProjectSections::DESIGN !== $route['section'] ) {
			return;
		}

		$project = $this->authorization->resolve_viewable_project( $route['project_id'] );
		if ( ! is_array( $project ) || ! ProjectEntitlement::is_project_usable( $project ) ) {
			return;
		}

		$this->poster_assets->enqueue_account_preview( $project );
	}
}
