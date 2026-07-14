<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Delivery;

use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Domain\Guest\InvitationStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectEntitlement;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;

/**
 * Queues guest invitation e-mails for eligible guests.
 */
final class InvitationSendService {

	public function __construct(
		private GuestRepository $guests,
		private DeliveryQueueService $queue
	) {}

	/**
	 * @param array<string, mixed> $project
	 * @param list<int>            $guest_ids
	 * @return array{success:bool,queued:int,skipped:int,error?:string}
	 */
	public function send_to_guests( array $project, array $guest_ids, bool $resend = false ): array {
		if ( ! ProjectEntitlement::can_edit_project( $project ) ) {
			return [ 'success' => false, 'queued' => 0, 'skipped' => 0, 'error' => 'entitlement_denied' ];
		}

		if ( PublicationStatus::PUBLISHED !== (string) ( $project['publication_status'] ?? '' ) ) {
			return [ 'success' => false, 'queued' => 0, 'skipped' => 0, 'error' => 'not_published' ];
		}

		$scope  = $resend ? (string) time() : 'initial';
		$queued = 0;
		$skipped = 0;

		foreach ( $guest_ids as $guest_id ) {
			$guest = $this->guests->find_by_id_for_project( (int) $guest_id, (int) $project['project_id'] );
			if ( ! is_array( $guest ) ) {
				++$skipped;
				continue;
			}

			if ( '' === sanitize_email( (string) ( $guest['email'] ?? '' ) ) ) {
				++$skipped;
				continue;
			}

			if ( null !== ( $guest['archived_at_utc'] ?? null ) && '' !== (string) $guest['archived_at_utc'] ) {
				++$skipped;
				continue;
			}

			if ( ! $resend && InvitationStatus::SENT === (string) ( $guest['invitation_status'] ?? '' ) ) {
				++$skipped;
				continue;
			}

			$delivery_id = $this->queue->queue_guest_invitation( $guest, $scope );
			if ( $delivery_id > 0 ) {
				++$queued;
			} else {
				++$skipped;
			}
		}

		return [
			'success' => $queued > 0,
			'queued'  => $queued,
			'skipped' => $skipped,
		];
	}
}
