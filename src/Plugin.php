<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations;

use PrikOgStreg\OnlineInvitations\Admin\DeliveryFailures;
use PrikOgStreg\OnlineInvitations\Admin\Capabilities;
use PrikOgStreg\OnlineInvitations\Admin\ProjectImportRetry;
use PrikOgStreg\OnlineInvitations\Admin\ProjectSupportRegistrar;
use PrikOgStreg\OnlineInvitations\Admin\Notices;
use PrikOgStreg\OnlineInvitations\Admin\ProjectPostType;
use PrikOgStreg\OnlineInvitations\Bootstrap\Activation;
use PrikOgStreg\OnlineInvitations\Bootstrap\Deactivation;
use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Database\DatabaseBootstrap;
use PrikOgStreg\OnlineInvitations\Database\ProjectDomainCleanup;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Storage\StorageRegistry;
use PrikOgStreg\OnlineInvitations\Api\RestRegistrar;
use PrikOgStreg\OnlineInvitations\Privacy\PrivacyRegistrar;
use PrikOgStreg\OnlineInvitations\MyAccount\MyAccountRegistrar;
use PrikOgStreg\OnlineInvitations\Public\PublicRegistrar;
use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;
use PrikOgStreg\OnlineInvitations\WooCommerce\CartCheckoutRegistrar;
use PrikOgStreg\OnlineInvitations\WooCommerce\Compatibility;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductTypeRegistrar;
use PrikOgStreg\OnlineInvitations\WooCommerce\Orders\ProjectOrderRegistrar;
use PrikOgStreg\OnlineInvitations\Scheduling\SchedulerRegistrar;
use PrikOgStreg\OnlineInvitations\WooCommerce\Emails\EmailRegistry;

/**
 * Root plugin orchestrator — explicit registrars, no service container.
 */
final class Plugin {

	private static ?self $instance = null;

	private BuilderService $builder;

	private ?RepositoryRegistry $repositories = null;

	private StorageRegistry $storage;

	private ?ProjectOrderRegistrar $project_orders = null;

	private TemplateLoader $templates;

	private function __construct() {
		$this->builder   = new BuilderService();
		$this->storage   = new StorageRegistry();
		$this->templates = new TemplateLoader();
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot(): void {
		register_activation_hook( PKS_OI_PLUGIN_FILE, [ Activation::class, 'run' ] );
		register_deactivation_hook( PKS_OI_PLUGIN_FILE, [ Deactivation::class, 'run' ] );

		load_plugin_textdomain(
			PKS_OI_TEXT_DOMAIN,
			false,
			dirname( plugin_basename( PKS_OI_PLUGIN_FILE ) ) . '/languages'
		);

		Capabilities::register_for_roles();

		$this->register_features();
	}

	private function register_features(): void {
		global $wpdb;

		$this->repositories = new RepositoryRegistry( $wpdb );

		( new Compatibility() )->register();
		( new ProductTypeRegistrar() )->register();
		( new CartCheckoutRegistrar( $this->builder ) )->register();
		$this->project_orders = new ProjectOrderRegistrar( $this->repositories, $this->builder, $this->storage );
		$this->project_orders->register();
		( new ProjectImportRetry( $this->project_orders->projects() ) )->register();
		( new ProjectSupportRegistrar( $this->repositories, $this->builder, $this->storage, $this->templates ) )->register();
		( new DatabaseBootstrap() )->register();
		$this->storage->bootstrap()->register();
		( new ProjectPostType() )->register();
		( new ProjectDomainCleanup(
			$this->repositories->projects(),
			$this->repositories->guests(),
			$this->repositories->wishlist_items(),
			$this->repositories->wishlist_reservations(),
			$this->repositories->photos(),
			$this->repositories->deliveries(),
			$this->repositories->events(),
			$this->storage->project_storage()
		) )->register();
		( new Notices( $this->builder ) )->register();
		$this->templates->register();
		( new MyAccountRegistrar( $this->repositories, $this->builder, $this->storage, $this->templates ) )->register();
		( new RestRegistrar( $this->repositories, $this->builder, $this->storage ) )->register();
		( new PublicRegistrar( $this->repositories, $this->builder, $this->storage, $this->templates ) )->register();
		( new SchedulerRegistrar( $this->repositories ) )->register();
		( new EmailRegistry() )->register();
		( new DeliveryFailures( $this->repositories->projects(), $this->repositories->deliveries() ) )->register();
		( new PrivacyRegistrar( $this->repositories, $this->storage ) )->register();

		$this->builder->register();
	}

	public function builder(): BuilderService {
		return $this->builder;
	}

	public function repositories(): RepositoryRegistry {
		if ( null === $this->repositories ) {
			global $wpdb;
			$this->repositories = new RepositoryRegistry( $wpdb );
		}

		return $this->repositories;
	}

	public function storage(): StorageRegistry {
		return $this->storage;
	}
}
