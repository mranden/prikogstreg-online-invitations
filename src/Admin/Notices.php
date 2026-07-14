<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin;

use PrikOgStreg\OnlineInvitations\Bootstrap\Requirements;
use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Database\Migrator;

/**
 * Admin notices for dependency and integration status.
 */
final class Notices {

	public function __construct(
		private BuilderService $builder
	) {}

	public function register(): void {
		add_action( 'admin_notices', [ $this, 'render_integration_notice' ] );
		add_action( 'admin_notices', [ $this, 'render_action_scheduler_notice' ] );
		add_action( 'admin_notices', [ $this, 'render_migration_notice' ] );
	}

	public function render_integration_notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( $this->builder->is_available() ) {
			return;
		}

		if ( $this->builder->is_integration_incompatible() ) {
			$this->print_notice(
				'error',
				__( 'Prikogstreg Online Invitations detected an incompatible PDF Builder integration service. Online invitation features are disabled until the adapter is updated.', 'prikogstreg-online-invitations' )
			);

			return;
		}

		if ( ! $this->builder->is_integration_registered() ) {
			$this->print_notice(
				'warning',
				__( 'Prikogstreg Online Invitations is waiting for the PDF Builder integration adapter (bpp/integration/service). Online invitation features are not active yet.', 'prikogstreg-online-invitations' )
			);

			return;
		}

		$this->print_notice(
			'warning',
			__( 'The PDF Builder integration adapter is not available. Online invitation features are disabled.', 'prikogstreg-online-invitations' )
		);
	}

	public function render_action_scheduler_notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( Requirements::action_scheduler_available() ) {
			return;
		}

		$this->print_notice(
			'warning',
			__( 'Action Scheduler was not detected. Scheduled invitation tasks will not run until WooCommerce Action Scheduler is available.', 'prikogstreg-online-invitations' )
		);
	}

	public function render_migration_notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$error = get_option( Migrator::OPTION_MIGRATION_ERROR, '' );

		if ( '' === $error ) {
			return;
		}

		$this->print_notice(
			'error',
			__( 'Prikogstreg Online Invitations could not complete a database migration. Online invitation data may be unavailable until the migration succeeds.', 'prikogstreg-online-invitations' )
		);
	}

	private function print_notice( string $type, string $message ): void {
		printf(
			'<div class="notice notice-%1$s"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}
}
