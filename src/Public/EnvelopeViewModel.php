<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

use PrikOgStreg\OnlineInvitations\Domain\Rsvp\RsvpFormViewModel;
use PrikOgStreg\OnlineInvitations\Storage\EnvelopeManifest;
use PrikOgStreg\OnlineInvitations\Storage\ProjectStorage;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\AttachmentValidator;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;

/**
 * View model for the animated envelope and published invitation body.
 */
final class EnvelopeViewModel {

	/**
	 * @param list<array{key:string,label:string,enabled:bool}> $sections
	 * @param list<array{index:int,html:string}>                $invitation_pages
	 * @param array{width:int,height:int,orientation:string}    $poster
	 * @param array<string, mixed>                              $rsvp_form
	 * @param array<string, mixed>                              $wishlist
	 * @param array<string, mixed>                              $photos
	 */
	public function __construct(
		public readonly string $envelope_preset,
		public readonly string $background_preset,
		public readonly string $envelope_image_url,
		public readonly int $envelope_image_width,
		public readonly int $envelope_image_height,
		public readonly string $addressee_label,
		public readonly string $link_type,
		public readonly string $invitation_html,
		public readonly array $invitation_pages,
		public readonly int $page_count,
		public readonly array $poster,
		public readonly string $event_title,
		public readonly bool $track_opens,
		public readonly array $sections,
		public readonly array $rsvp_form,
		public readonly array $wishlist,
		public readonly array $photos,
		public readonly string $invitation_token
	) {}

	public function has_multiple_pages(): bool {
		return $this->page_count > 1;
	}

	public static function from_resolution(
		TokenResolution $resolution,
		PublicInvitationContent $content,
		string $raw_token = '',
		array $wishlist = [],
		array $photos = [],
		?EnvelopeImageResolver $envelope_images = null,
		?ProjectStorage $storage = null
	): self {
		$project = $resolution->project();
		$guest   = $resolution->guest();

		$envelope   = 'classic';
		$background = 'neutral';

		$storage_uuid = (string) ( $project['storage_uuid'] ?? '' );
		if ( '' !== $storage_uuid && null !== $storage ) {
			$manifest = $storage->try_read_envelope_manifest( $storage_uuid );
			if ( $manifest instanceof EnvelopeManifest ) {
				if ( ProductMeta::is_envelope_preset_valid( $manifest->preset ) ) {
					$envelope = $manifest->preset;
				}
				if ( ProductMeta::is_background_preset_valid( $manifest->background_preset ) ) {
					$background = $manifest->background_preset;
				}
			}
		}

		if ( 'classic' === $envelope && ProductMeta::is_envelope_preset_valid( (string) ( $project['envelope_preset'] ?? '' ) ) ) {
			$envelope = (string) $project['envelope_preset'];
		}

		if ( 'neutral' === $background && ProductMeta::is_background_preset_valid( (string) ( $project['background_preset'] ?? '' ) ) ) {
			$background = (string) $project['background_preset'];
		}

		$envelope_image_url    = '';
		$envelope_image_width  = 0;
		$envelope_image_height = 0;

		if ( null !== $envelope_images ) {
			$envelope_image_url = $envelope_images->resolve_url( $project, $raw_token );
			$dimensions         = $envelope_images->resolve_dimensions( $project );
			$envelope_image_width  = $dimensions['width'];
			$envelope_image_height = $dimensions['height'];
		} else {
			$envelope_image_url = AttachmentValidator::image_url( max( 0, (int) ( $project['envelope_image_id'] ?? 0 ) ) );
		}

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
			$envelope_image_url,
			$envelope_image_width,
			$envelope_image_height,
			$label,
			$resolution->type(),
			$content->first_page_html(),
			$content->pages,
			$content->page_count,
			$content->poster,
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
