<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Delivery;

use PrikOgStreg\OnlineInvitations\Database\Repositories\DeliveryRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestTokenService;
use PrikOgStreg\OnlineInvitations\Domain\Guest\InvitationStatus;
use PrikOgStreg\OnlineInvitations\Scheduling\ActionSchedulerBridge;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

/**
 * Processes queued delivery rows and dispatches WooCommerce e-mails.
 *
 * "Sent" means accepted by wp_mail / the active mailer — not inbox delivery.
 */
final class DeliverySendService {

	public function __construct(
		private DeliveryRepository $deliveries,
		private DeliveryRecipientResolver $resolver,
		private GuestRepository $guests,
		private GuestTokenService $guest_tokens
	) {}

	public function process_delivery( int $delivery_id ): bool {
		$delivery = $this->deliveries->find_by_id( $delivery_id );
		if ( ! is_array( $delivery ) ) {
			return false;
		}

		$status = (string) ( $delivery['status'] ?? '' );
		if ( in_array( $status, [ DeliveryStatus::SENT, DeliveryStatus::CANCELLED, DeliveryStatus::SKIPPED ], true ) ) {
			return DeliveryStatus::SENT === $status;
		}

		$this->deliveries->update(
			$delivery_id,
			[
				'status'           => DeliveryStatus::PROCESSING,
				'started_at_utc'   => UtcDateTime::now(),
				'attempt_count'    => (int) ( $delivery['attempt_count'] ?? 0 ) + 1,
			]
		);

		$context = $this->resolver->resolve( $delivery );
		if ( empty( $context['success'] ) ) {
			return $this->finish_skip_or_fail( $delivery_id, (string) ( $context['error'] ?? 'resolve_failed' ), $this->is_skip_error( (string) ( $context['error'] ?? '' ) ) );
		}

		if ( $this->needs_guest_url_refresh( $delivery, $context ) ) {
			$guest = $context['guest'] ?? null;
			if ( is_array( $guest ) ) {
				$rotated = $this->guest_tokens->rotate( $guest );
				$context['invitation_url'] = $rotated['url'];
			}
		}

		$sent = (bool) apply_filters( 'pks_oi_delivery_send', false, $delivery, $context );
		if ( ! $sent ) {
			$sent = $this->dispatch_wc_email( $delivery, $context );
		}

		if ( $sent ) {
			$this->deliveries->update(
				$delivery_id,
				[
					'status'       => DeliveryStatus::SENT,
					'sent_at_utc'  => UtcDateTime::now(),
					'last_error_code' => null,
					'last_error_message' => null,
				]
			);
			$this->after_sent( $delivery, $context );

			return true;
		}

		return $this->schedule_retry_or_fail( $delivery_id, $delivery, 'send_failed' );
	}

	/**
	 * @param array<string, mixed> $delivery
	 * @param array<string, mixed> $context
	 */
	private function dispatch_wc_email( array $delivery, array $context ): bool {
		if ( ! function_exists( 'WC' ) || ! function_exists( 'wc_get_container' ) ) {
			return $this->fallback_wp_mail( $delivery, $context );
		}

		$mailer = WC()->mailer();
		if ( ! is_object( $mailer ) || ! method_exists( $mailer, 'get_emails' ) ) {
			return $this->fallback_wp_mail( $delivery, $context );
		}

		$email_id = $this->email_id_for_type( (string) ( $delivery['delivery_type'] ?? '' ) );
		if ( '' === $email_id ) {
			return false;
		}

		$emails = $mailer->get_emails();
		if ( ! is_array( $emails ) || ! isset( $emails[ $email_id ] ) ) {
			return $this->fallback_wp_mail( $delivery, $context );
		}

		$email = $emails[ $email_id ];
		if ( ! is_object( $email ) || ! method_exists( $email, 'trigger' ) ) {
			return false;
		}

		$email->trigger( $delivery, $context );

		return true;
	}

	/**
	 * @param array<string, mixed> $delivery
	 * @param array<string, mixed> $context
	 */
	private function fallback_wp_mail( array $delivery, array $context ): bool {
		if ( ! function_exists( 'wp_mail' ) ) {
			return false;
		}

		$to      = (string) ( $context['email'] ?? '' );
		$subject = sprintf(
			/* translators: %s: delivery type */
			__( 'Invitation update (%s)', 'prikogstreg-online-invitations' ),
			(string) ( $delivery['delivery_type'] ?? '' )
		);
		$body    = (string) ( $context['invitation_url'] ?? $context['account_url'] ?? '' );

		return wp_mail( $to, $subject, $body );
	}

	private function email_id_for_type( string $type ): string {
		return match ( $type ) {
			DeliveryType::WELCOME           => 'pks_oi_project_welcome',
			DeliveryType::DEMO              => 'pks_oi_demo_invitation',
			DeliveryType::GUEST_INVITATION  => 'pks_oi_guest_invitation',
			DeliveryType::RSVP_REMINDER     => 'pks_oi_rsvp_reminder',
			DeliveryType::RSVP_CONFIRMATION => 'pks_oi_rsvp_confirmation',
			DeliveryType::ORGANIZER_RSVP    => 'pks_oi_organizer_rsvp',
			DeliveryType::PHOTO_NOTIFICATION => 'pks_oi_photo_upload',
			DeliveryType::PHOTO_SHARE_INVITE   => 'pks_oi_photo_share_invite',
			default                         => '',
		};
	}

	/**
	 * @param array<string, mixed> $delivery
	 * @param array<string, mixed> $context
	 */
	private function after_sent( array $delivery, array $context ): void {
		$type = (string) ( $delivery['delivery_type'] ?? '' );
		if ( DeliveryType::GUEST_INVITATION === $type && is_array( $context['guest'] ?? null ) ) {
			$this->guests->update(
				(int) $context['guest']['guest_id'],
				[
					'invitation_status' => InvitationStatus::SENT,
					'last_sent_at_utc'  => UtcDateTime::now(),
					'first_sent_at_utc' => (string) ( $context['guest']['first_sent_at_utc'] ?? '' ) !== ''
						? $context['guest']['first_sent_at_utc']
						: UtcDateTime::now(),
				]
			);
		}

		do_action( 'pks_oi_delivery_sent', (int) ( $delivery['delivery_id'] ?? 0 ), $type );
	}

	/**
	 * @param array<string, mixed> $delivery
	 * @param array<string, mixed> $context
	 */
	private function needs_guest_url_refresh( array $delivery, array $context ): bool {
		if ( DeliveryType::GUEST_INVITATION !== (string) ( $delivery['delivery_type'] ?? '' ) ) {
			return false;
		}

		return '' === (string) ( $context['invitation_url'] ?? '' );
	}

	private function is_skip_error( string $error ): bool {
		return in_array(
			$error,
			[
				'already_responded',
				'no_email',
				'no_deadline',
				'project_unavailable',
				'guest_revoked',
				'invitation_url_unavailable',
			],
			true
		);
	}

	private function finish_skip_or_fail( int $delivery_id, string $error, bool $skip ): bool {
		$this->deliveries->update(
			$delivery_id,
			[
				'status'             => $skip ? DeliveryStatus::SKIPPED : DeliveryStatus::FAILED,
				'failed_at_utc'      => UtcDateTime::now(),
				'last_error_code'    => $error,
				'last_error_message' => $error,
			]
		);

		return false;
	}

	/**
	 * @param array<string, mixed> $delivery
	 */
	private function schedule_retry_or_fail( int $delivery_id, array $delivery, string $error ): bool {
		$attempts = (int) ( $delivery['attempt_count'] ?? 0 ) + 1;
		if ( $attempts >= SchedulerMeta::MAX_SEND_ATTEMPTS ) {
			$this->deliveries->update(
				$delivery_id,
				[
					'status'             => DeliveryStatus::FAILED,
					'failed_at_utc'      => UtcDateTime::now(),
					'last_error_code'    => $error,
					'last_error_message' => $error,
				]
			);

			return false;
		}

		$delay = SchedulerMeta::RETRY_DELAYS[ min( $attempts - 1, count( SchedulerMeta::RETRY_DELAYS ) - 1 ) ] ?? 300;
		$this->deliveries->update(
			$delivery_id,
			[
				'status'             => DeliveryStatus::QUEUED,
				'last_error_code'    => $error,
				'last_error_message' => $error,
			]
		);

		ActionSchedulerBridge::schedule_single(
			SchedulerMeta::SEND_INVITATION,
			[ $delivery_id ],
			time() + $delay,
			'send:' . $delivery_id . ':retry:' . $attempts
		);

		return false;
	}
}
