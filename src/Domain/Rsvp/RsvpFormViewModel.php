<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Rsvp;

use PrikOgStreg\OnlineInvitations\Domain\Guest\RsvpStatus;
use PrikOgStreg\OnlineInvitations\Public\TokenResolution;

/**
 * Builds RSVP form configuration for the public envelope view.
 */
final class RsvpFormViewModel {

	/**
	 * @param array<string, mixed> $config
	 */
	public function __construct(
		public readonly array $config
	) {}

	public static function from_resolution( TokenResolution $resolution ): self {
		$project = $resolution->project();
		$guest   = $resolution->guest();
		$is_open = RsvpDeadlinePolicy::is_open( $project );

		$config = [
			'link_type'               => $resolution->type(),
			'is_open'                 => $is_open,
			'deadline_label'          => RsvpDeadlinePolicy::deadline_label( $project ),
			'attendee_count_enabled'  => ! empty( $project['attendee_count_enabled'] ),
			'comment_enabled'         => ! empty( $project['comment_enabled'] ),
			'dietary_notes_enabled'   => ! empty( $project['dietary_notes_enabled'] ),
			'attending'               => null,
			'attendee_count'          => null,
			'invited_attendee_count'  => null,
			'rsvp_comment'            => '',
			'dietary_notes'           => '',
			'display_name'            => '',
			'email'                   => '',
			'has_prior_response'      => false,
			'current_status'          => RsvpStatus::PENDING,
			'rest_url'                => '',
			'rest_nonce'              => '',
		];

		if ( $resolution->is_personal() && is_array( $guest ) ) {
			$status = (string) ( $guest['rsvp_status'] ?? RsvpStatus::PENDING );
			$config['current_status']     = $status;
			$config['has_prior_response'] = RsvpStatus::PENDING !== $status;
			$config['attending']          = RsvpStatus::ATTENDING === $status ? true : ( RsvpStatus::DECLINED === $status ? false : null );
			$config['attendee_count']     = $guest['attendee_count'] ?? null;
			$config['rsvp_comment']       = (string) ( $guest['rsvp_comment'] ?? '' );
			$config['dietary_notes']      = (string) ( $guest['dietary_notes'] ?? '' );
			$config['display_name']       = (string) ( $guest['display_name'] ?? '' );
			$config['email']              = (string) ( $guest['email'] ?? '' );

			if (
				RsvpStatus::PENDING === $status
				&& null !== ( $guest['attendee_count'] ?? null )
				&& (int) $guest['attendee_count'] > 0
			) {
				$config['invited_attendee_count'] = (int) $guest['attendee_count'];
			}
		}

		return new self( $config );
	}
}
