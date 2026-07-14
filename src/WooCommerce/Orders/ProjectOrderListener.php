<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\Orders;

use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectEntitlement;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectService;

/**
 * Creates projects when orders reach qualifying statuses.
 */
final class ProjectOrderListener {

	public function __construct(
		private ProjectService $projects
	) {}

	public function register(): void {
		foreach ( ProjectEntitlement::qualifying_statuses() as $status ) {
			add_action( 'woocommerce_order_status_' . $status, [ $this, 'handle_qualifying_status' ], 20, 2 );
		}
	}

	/**
	 * @param int    $order_id Order ID.
	 * @param object $order    WooCommerce order.
	 */
	public function handle_qualifying_status( int $order_id, $order = null ): void {
		if ( ! is_object( $order ) && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! is_object( $order ) ) {
			return;
		}

		$this->projects->process_order( $order );
	}
}
