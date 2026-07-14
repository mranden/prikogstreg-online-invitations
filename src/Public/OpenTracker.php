<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

use PrikOgStreg\OnlineInvitations\Admin\Capabilities;
use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Domain\Guest\InvitationStatus;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

/**
 * Records first/last open timestamps for personal invitation links.
 */
final class OpenTracker {

	public function __construct(
		private GuestRepository $guests
	) {}

	public function maybe_track( TokenResolution $resolution ): void {
		if ( ! $resolution->is_personal() ) {
			return;
		}

		$guest = $resolution->guest();
		if ( ! is_array( $guest ) ) {
			return;
		}

		if ( $this->is_owner_or_staff_preview( $resolution->project() ) ) {
			return;
		}

		if ( $this->is_prefetch_or_bot_request() ) {
			return;
		}

		$guest_id = (int) ( $guest['guest_id'] ?? 0 );
		if ( $guest_id <= 0 ) {
			return;
		}

		$now   = UtcDateTime::now();
		$data  = [
			'last_opened_at_utc' => $now,
			'open_count'           => max( 0, (int) ( $guest['open_count'] ?? 0 ) ) + 1,
		];

		if ( '' === (string) ( $guest['first_opened_at_utc'] ?? '' ) ) {
			$data['first_opened_at_utc'] = $now;
		}

		if ( InvitationStatus::NOT_SENT === (string) ( $guest['invitation_status'] ?? InvitationStatus::NOT_SENT ) ) {
			$data['invitation_status'] = InvitationStatus::OPENED;
		}

		$this->guests->update( $guest_id, $data );
		do_action( 'pks_oi_invitation_opened', $guest_id, (int) ( $guest['project_id'] ?? 0 ) );
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function is_owner_or_staff_preview( array $project ): bool {
		if ( ! function_exists( 'get_current_user_id' ) ) {
			return false;
		}

		$user_id = (int) get_current_user_id();
		if ( $user_id <= 0 ) {
			return false;
		}

		if ( $user_id === (int) ( $project['user_id'] ?? 0 ) ) {
			return true;
		}

		return function_exists( 'current_user_can' ) && current_user_can( Capabilities::SUPPORT );
	}

	private function is_prefetch_or_bot_request(): bool {
		$purpose = strtolower( (string) ( $_SERVER['HTTP_SEC_PURPOSE'] ?? '' ) );
		if ( str_contains( $purpose, 'prefetch' ) || str_contains( $purpose, 'preview' ) ) {
			return true;
		}

		$moz = strtolower( (string) ( $_SERVER['HTTP_X_MOZ'] ?? '' ) );
		if ( 'prefetch' === $moz ) {
			return true;
		}

		return false;
	}
}
