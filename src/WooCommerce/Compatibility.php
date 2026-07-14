<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce;

/**
 * WooCommerce HPOS-only compatibility.
 *
 * Online Invitations requires High-Performance Order Storage (custom order tables).
 * Legacy post-based order storage is not supported.
 */
final class Compatibility {

	public function register(): void {
		add_action( 'before_woocommerce_init', [ self::class, 'declare_hpos_compatibility' ] );
	}

	public static function declare_hpos_compatibility(): void {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			PKS_OI_PLUGIN_FILE,
			true
		);
	}

	public static function is_hpos_enabled(): bool {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return false;
		}

		return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}
}
