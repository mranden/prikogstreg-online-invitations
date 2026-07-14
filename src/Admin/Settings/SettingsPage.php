<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin\Settings;

use PrikOgStreg\OnlineInvitations\Admin\Capabilities;
use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;

/**
 * Plugin operational settings and diagnostics (no secrets).
 */
final class SettingsPage {

	public function __construct(
		private TemplateLoader $templates
	) {}

	public function register(): void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function register_settings(): void {
		register_setting(
			'pks_oi_settings',
			'pks_oi_admin_diagnostics_enabled',
			[
				'type'              => 'boolean',
				'sanitize_callback' => static fn( $value ): bool => (bool) $value,
				'default'           => false,
			]
		);
	}

	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'You do not have permission to manage settings.', 'prikogstreg-online-invitations' ) );
		}

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['pks_oi_settings_submit'] ) ) {
			check_admin_referer( 'pks_oi_settings' );
			$enabled = ! empty( $_POST['pks_oi_admin_diagnostics_enabled'] );
			update_option( 'pks_oi_admin_diagnostics_enabled', $enabled );
			add_settings_error( 'pks_oi_admin', 'settings_saved', __( 'Settings saved.', 'prikogstreg-online-invitations' ), 'updated' );
		}

		$context = [
			'plugin_version'    => defined( 'PKS_OI_VERSION' ) ? PKS_OI_VERSION : '',
			'db_version'        => (string) get_option( 'pks_oi_db_version', '' ),
			'diagnostics'       => (bool) get_option( 'pks_oi_admin_diagnostics_enabled', false ),
			'hpos_enabled'      => class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
				&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(),
		];

		echo '<div class="wrap pks-oi-admin-projects">';
		echo '<h1>' . esc_html__( 'Online Invitations settings', 'prikogstreg-online-invitations' ) . '</h1>';
		settings_errors( 'pks_oi_admin' );
		$this->templates->render( 'admin/settings', $context );
		echo '</div>';
	}
}
