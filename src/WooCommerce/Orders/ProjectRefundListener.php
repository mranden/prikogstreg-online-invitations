<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\Orders;

use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectRestrictionService;

/**
 * Restricts projects when invitation line items are fully refunded or orders are cancelled.
 */
final class ProjectRefundListener {

	public function __construct(
		private ProjectRepository $projects,
		private OrderRefundDetector $detector,
		private ProjectRestrictionService $restriction
	) {}

	public function register(): void {
		add_action( 'woocommerce_order_refunded', [ $this, 'handle_order_refunded' ], 20, 2 );
		add_action( 'woocommerce_order_status_cancelled', [ $this, 'handle_order_cancelled' ], 20, 2 );
		add_action( 'woocommerce_order_status_failed', [ $this, 'handle_order_cancelled' ], 20, 2 );
	}

	/**
	 * @param int    $order_id  Order ID.
	 * @param int    $refund_id Refund ID.
	 */
	public function handle_order_refunded( int $order_id, int $refund_id ): void {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! is_object( $order ) ) {
			return;
		}

		foreach ( $this->projects->list_by_order_id( $order_id ) as $project ) {
			if ( $this->detector->is_invitation_line_fully_refunded( $project ) ) {
				$this->restriction->restrict( $project, 'full_line_refund', 'refund_hook' );
			}
		}
	}

	/**
	 * @param int    $order_id Order ID.
	 * @param object $order    WooCommerce order.
	 */
	public function handle_order_cancelled( int $order_id, $order = null ): void {
		unset( $order );

		foreach ( $this->projects->list_by_order_id( $order_id ) as $project ) {
			$this->restriction->restrict( $project, 'order_cancelled_or_failed', 'order_status' );
		}
	}
}
