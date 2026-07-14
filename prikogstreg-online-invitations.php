<?php
/**
 * Plugin Name:       Prikogstreg Online Invitations
 * Plugin URI:        https://prikogstreg.dk/
 * Description:       WooCommerce online invitation projects with PDF Builder integration.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Prik & Streg
 * Text Domain:       prikogstreg-online-invitations
 * Domain Path:       /languages
 *
 * @package PrikOgStreg\OnlineInvitations
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PKS_OI_VERSION', '0.1.0' );
define( 'PKS_OI_DB_VERSION', '1' );
define( 'PKS_OI_PLUGIN_FILE', __FILE__ );
define( 'PKS_OI_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'PKS_OI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PKS_OI_TEXT_DOMAIN', 'prikogstreg-online-invitations' );

$autoload = PKS_OI_PLUGIN_PATH . 'vendor/autoload.php';

if ( ! file_exists( $autoload ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__(
					'Prikogstreg Online Invitations is missing Composer dependencies. Run composer install in the plugin directory.',
					'prikogstreg-online-invitations'
				)
			);
		}
	);

	return;
}

require_once $autoload;

\PrikOgStreg\OnlineInvitations\Bootstrap\Requirements::boot();
