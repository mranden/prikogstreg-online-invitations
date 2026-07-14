<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce;

/**
 * WooCommerce feature compatibility declarations.
 */
final class Compatibility {

	public function register(): void {
		add_action( 'before_woocommerce_init', [ $this, 'declare_hpos_compatibility' ] );
	}

	public function declare_hpos_compatibility(): void {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			PKS_OI_PLUGIN_FILE,
			true
		);
	}
}
