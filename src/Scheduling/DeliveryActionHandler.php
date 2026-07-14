<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Scheduling;

use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliverySendService;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\SchedulerMeta;

/**
 * Action Scheduler callbacks for delivery sends.
 */
final class DeliveryActionHandler {

	public function __construct(
		private DeliverySendService $sender
	) {}

	public function register(): void {
		add_action( SchedulerMeta::SEND_INVITATION, [ $this, 'handle_send' ], 10, 1 );
		add_action( SchedulerMeta::SEND_REMINDER, [ $this, 'handle_send' ], 10, 1 );

		ActionSchedulerBridge::register_sync_handler(
			SchedulerMeta::SEND_INVITATION,
			[ $this, 'handle_send' ]
		);
		ActionSchedulerBridge::register_sync_handler(
			SchedulerMeta::SEND_REMINDER,
			[ $this, 'handle_send' ]
		);
	}

	public function handle_send( int $delivery_id ): void {
		$this->sender->process_delivery( $delivery_id );
	}
}
