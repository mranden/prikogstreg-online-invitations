<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

use PrikOgStreg\OnlineInvitations\Domain\Rsvp\RsvpFormViewModel;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;

/**
 * View model for the animated envelope and published invitation body.
 */
final class EnvelopeViewModel {

	/**
	 * @param list<array{key:string,label:string,enabled:bool}> $sections
	 * @param array<string, mixed>                               $rsvp_form
	 * @param array<string, mixed>                               $wishlist
	 * @param array<string, mixed>                               $photos
	 */
	public function __construct(
		public readonly string $envelope_preset,
		public readonly string $background_preset,
		public readonly string $addressee_label,
		public readonly string $link_type,
		public readonly string $invitation_html,
		public readonly string $event_title,
		public readonly bool $track_opens,
		public readonly array $sections,
		public readonly array $rsvp_form,
		public readonly array $wishlist,
		public readonly array $photos,
		public readonly string $invitation_token
	) {}

	public static function from_resolution( TokenResolution $resolution, string $invitation_html, string $raw_token = '', array $wishlist = [], array $photos = [] ): self {
		$project = $resolution->project();
		$guest   = $resolution->guest();

		$envelope = ProductMeta::is_envelope_preset_valid( (string) ( $project['envelope_preset'] ?? '' ) )
			? (string) $project['envelope_preset']
			: 'classic';

		$background = ProductMeta::is_background_preset_valid( (string) ( $project['background_preset'] ?? '' ) )
			? (string) $project['background_preset']
			: 'neutral';

		if ( $resolution->is_personal() && is_array( $guest ) ) {
			$label = trim( (string) ( $guest['display_name'] ?? '' ) );
			if ( '' === $label ) {
				$label = __( 'Dear guest', 'prikogstreg-online-invitations' );
			}
		} else {
			$label = __( 'You are invited', 'prikogstreg-online-invitations' );
		}

		$sections = [];
		$sections[] = [
			'key'     => 'rsvp',
			'label'   => __( 'RSVP', 'prikogstreg-online-invitations' ),
			'enabled' => true,
		];

		if ( ! empty( $project['internal_wishlist_enabled'] ) ) {
			$sections[] = [
				'key'     => 'wishlist',
				'label'   => __( 'Wishlist', 'prikogstreg-online-invitations' ),
				'enabled' => true,
			];
		}

		if ( ! empty( $project['guest_photos_enabled'] ) ) {
			$sections[] = [
				'key'     => 'photos',
				'label'   => __( 'Photos', 'prikogstreg-online-invitations' ),
				'enabled' => true,
			];
		}

		$rsvp_form = RsvpFormViewModel::from_resolution( $resolution )->config;
		if ( '' !== $raw_token && function_exists( 'rest_url' ) ) {
			$rsvp_form['rest_url']   = rest_url( 'prikogstreg-online-invitations/v1/public/' . rawurlencode( $raw_token ) . '/rsvp' );
			$rsvp_form['rest_nonce'] = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( 'wp_rest' ) : '';
		}

		return new self(
			$envelope,
			$background,
			$label,
			$resolution->type(),
			$invitation_html,
			trim( (string) ( $project['event_title'] ?? '' ) ),
			$resolution->is_personal(),
			$sections,
			$rsvp_form,
			$wishlist,
			$photos,
			$raw_token
		);
	}
}
