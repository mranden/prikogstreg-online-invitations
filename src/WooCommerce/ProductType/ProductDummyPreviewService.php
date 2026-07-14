<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\ProductType;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Domain\Guest\RsvpStatus;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoShareQrService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectTemplateFallback;
use PrikOgStreg\OnlineInvitations\Public\EnvelopeViewModel;
use PrikOgStreg\OnlineInvitations\Public\PosterDimensions;
use PrikOgStreg\OnlineInvitations\Public\PublicInvitationContent;
use PrikOgStreg\OnlineInvitations\Public\TokenResolution;

/**
 * Builds a synthetic public invitation preview from product admin settings.
 */
final class ProductDummyPreviewService {

	/**
	 * @return list<string>
	 */
	private function sample_guest_names(): array {
		return [
			'Anna Jensen',
			'Bo Hansen',
			'Clara Nielsen',
			'Ditte Larsen',
			'Erik Miller',
			'Freja Pedersen',
		];
	}

	/**
	 * @return list<array{title:string,description:string,quantity_requested:int,quantity_reserved:int,external_url:string}>
	 */
	private function sample_wishlist_catalog(): array {
		return [
			[
				'title'              => __( 'Flower bouquet', 'prikogstreg-online-invitations' ),
				'description'        => __( 'A seasonal bouquet for the host — any florist is fine.', 'prikogstreg-online-invitations' ),
				'quantity_requested' => 1,
				'quantity_reserved'  => 1,
				'external_url'       => '',
			],
			[
				'title'              => __( 'Gift card', 'prikogstreg-online-invitations' ),
				'description'        => __( 'A small gift card for a local bookshop or café.', 'prikogstreg-online-invitations' ),
				'quantity_requested' => 1,
				'quantity_reserved'  => 0,
				'external_url'       => '',
			],
			[
				'title'              => __( 'Board game', 'prikogstreg-online-invitations' ),
				'description'        => __( 'Family-friendly games we can play together after the party.', 'prikogstreg-online-invitations' ),
				'quantity_requested' => 1,
				'quantity_reserved'  => 0,
				'external_url'       => '',
			],
			[
				'title'              => __( 'Scented candles', 'prikogstreg-online-invitations' ),
				'description'        => __( 'Two matching candles in neutral colours.', 'prikogstreg-online-invitations' ),
				'quantity_requested' => 2,
				'quantity_reserved'  => 1,
				'external_url'       => '',
			],
			[
				'title'              => __( 'Children’s books', 'prikogstreg-online-invitations' ),
				'description'        => __( 'Picture books suitable for ages 3–7.', 'prikogstreg-online-invitations' ),
				'quantity_requested' => 3,
				'quantity_reserved'  => 0,
				'external_url'       => '',
			],
			[
				'title'              => __( 'Kitchen essentials', 'prikogstreg-online-invitations' ),
				'description'        => __( 'Oven mitts, tea towels, or other useful kitchen items.', 'prikogstreg-online-invitations' ),
				'quantity_requested' => 1,
				'quantity_reserved'  => 0,
				'external_url'       => '',
			],
		];
	}

	public function __construct(
		private BuilderService $builder,
		private ?PhotoShareQrService $qr = null
	) {
		$this->qr ??= new PhotoShareQrService();
	}

	public function is_available_for_product( object $product ): bool {
		if ( ! ProductMeta::is_online_invitation( $product ) ) {
			return false;
		}

		if ( ! ProductMeta::read_dummy_preview_enabled( $product ) ) {
			return false;
		}

		if ( method_exists( $product, 'is_visible' ) && ! $product->is_visible() ) {
			return false;
		}

		return true;
	}

	public function build_view_model( int $product_id ): ?EnvelopeViewModel {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( ! $product || ! $this->is_available_for_product( $product ) ) {
			return null;
		}

		$project   = $this->build_project( $product );
		$guest     = $this->build_guest( $product );
		$content   = $this->build_content( $product_id );
		$wishlist  = $this->build_wishlist( $product );
		$photos    = $this->build_photos( $product );
		$design    = EnvelopeDesign::resolve_for_product( $product );
		$resolution = new TokenResolution(
			TokenResolution::TYPE_PERSONAL,
			$project,
			$guest
		);

		$view = EnvelopeViewModel::from_resolution(
			$resolution,
			$content,
			'',
			$wishlist,
			$photos,
			null,
			null
		);

		return $this->apply_sample_section_labels(
			$this->apply_envelope_design( $view, $design )
		);
	}

	/**
	 * @return list<array{key:string,label:string,enabled:bool}>
	 */
	private function relabel_sample_sections( array $sections ): array {
		return array_map(
			static function ( array $section ): array {
				return match ( (string) ( $section['key'] ?? '' ) ) {
					'wishlist' => [
						...$section,
						'label' => __( 'Example wishlist', 'prikogstreg-online-invitations' ),
					],
					'photos' => [
						...$section,
						'label' => __( 'Example guest photos', 'prikogstreg-online-invitations' ),
					],
					default => $section,
				};
			},
			$sections
		);
	}

	private function apply_sample_section_labels( EnvelopeViewModel $view ): EnvelopeViewModel {
		$sections = $this->relabel_sample_sections( $view->sections );

		return new EnvelopeViewModel(
			$view->envelope_preset,
			$view->background_preset,
			$view->envelope_image_url,
			$view->envelope_image_width,
			$view->envelope_image_height,
			$view->addressee_label,
			$view->link_type,
			$view->invitation_html,
			$view->invitation_pages,
			$view->page_count,
			$view->poster,
			$view->event_title,
			$view->track_opens,
			$sections,
			$view->rsvp_form,
			$view->wishlist,
			$view->photos,
			$view->event_details,
			$view->invitation_token,
			$view->session_storage_key
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_project( object $product ): array {
		$product_id   = (int) $product->get_id();
		$days_ahead   = ProductMeta::read_dummy_event_days_ahead( $product );
		$reminder     = ProductMeta::read_reminder_offset_days( $product );
		$timezone     = 'Europe/Copenhagen';
		$zone         = new \DateTimeZone( $timezone );
		$event_start  = ( new \DateTimeImmutable( 'now', $zone ) )
			->modify( '+' . $days_ahead . ' days' )
			->setTime( 14, 0 );
		$event_end    = $event_start->setTime( 18, 0 );
		$rsvp_deadline = $event_start
			->modify( '-' . $reminder . ' days' )
			->setTime( 23, 59, 59 );
		$title        = ProductMeta::read_dummy_event_title( $product );
		if ( '' === $title && method_exists( $product, 'get_name' ) ) {
			$title = (string) $product->get_name();
		}

		$design = EnvelopeDesign::resolve_for_product( $product );

		return [
			'project_id'               => 0,
			'product_id'               => $product_id,
			'storage_uuid'             => '',
			'event_title'              => $title,
			'event_start_utc'          => $event_start->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
			'event_end_utc'            => $event_end->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
			'timezone'                 => $timezone,
			'venue_name'               => __( 'The Event Hall', 'prikogstreg-online-invitations' ),
			'venue_address_line1'      => __( '12 Main Street', 'prikogstreg-online-invitations' ),
			'venue_address_line2'      => '',
			'venue_postcode'           => '2100',
			'venue_city'               => __( 'Copenhagen East', 'prikogstreg-online-invitations' ),
			'venue_country'            => __( 'Denmark', 'prikogstreg-online-invitations' ),
			'practical_info'           => __( 'Parking is available in the courtyard behind the building. Children are welcome.', 'prikogstreg-online-invitations' ),
			'rsvp_deadline_utc'        => $rsvp_deadline->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
			'reminder_offset_days'     => $reminder,
			'envelope_preset'          => (string) ( $design['preset'] ?? 'classic' ),
			'background_preset'        => (string) ( $design['background_preset'] ?? 'neutral' ),
			'envelope_image_id'        => (int) ( $design['image_id'] ?? 0 ),
			'internal_wishlist_enabled'=> 1,
			'guest_photos_enabled'     => 1,
			'attendee_count_enabled'   => 1,
			'comment_enabled'          => 1,
			'dietary_notes_enabled'    => 1,
			'locale'                   => ProductMeta::read_default_locale( $product ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_guest( object $product ): array {
		$guest_count = ProductMeta::read_dummy_guest_count( $product );
		$names       = $this->sample_guest_names();
		$name        = $names[0];

		return [
			'guest_id'       => 0,
			'display_name'   => $name,
			'email'          => 'anna.jensen@example.com',
			'rsvp_status'    => RsvpStatus::PENDING,
			'attendee_count' => $guest_count,
			'rsvp_comment'   => '',
			'dietary_notes'  => '',
		];
	}

	private function build_content( int $product_id ): PublicInvitationContent {
		$fallback = new ProjectTemplateFallback( $this->builder );
		$state    = $fallback->resolve_for_product( $product_id );
		$pages    = is_array( $state['page'] ?? null ) ? $state['page'] : [];
		$formatted = [];
		$index     = 0;

		foreach ( $pages as $html ) {
			if ( ! is_string( $html ) || '' === trim( $html ) ) {
				continue;
			}

			$formatted[] = [
				'index' => $index,
				'html'  => $html,
			];
			++$index;
		}

		if ( [] === $formatted ) {
			$formatted[] = [
				'index' => 0,
				'html'  => $fallback->design_placeholder_page_html( $product_id ),
			];
		}

		$size   = (string) ( $state['size'] ?? 'a5' );
		$format = (string) ( $state['format'] ?? 'flat' );
		$poster = PosterDimensions::resolve( $size, $format, (string) ( $formatted[0]['html'] ?? '' ) );

		return new PublicInvitationContent(
			$formatted,
			$poster,
			count( $formatted )
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_wishlist( object $product ): array {
		$gift_count = max( 3, ProductMeta::read_dummy_gift_count( $product ) );
		$catalog    = $this->sample_wishlist_catalog();
		$images     = $this->sample_media_urls( $product );
		$items      = [];

		for ( $i = 0; $i < $gift_count; ++$i ) {
			$entry     = $catalog[ $i % count( $catalog ) ];
			$requested = max( 1, (int) ( $entry['quantity_requested'] ?? 1 ) );
			$reserved  = min( $requested, max( 0, (int) ( $entry['quantity_reserved'] ?? 0 ) ) );
			$image_url = [] !== $images ? $images[ $i % count( $images ) ] : '';

			$items[] = [
				'wishlist_item_id'     => $i + 1,
				'title'                => (string) $entry['title'],
				'description'          => (string) $entry['description'],
				'external_url'         => (string) ( $entry['external_url'] ?? '' ),
				'image_url'            => $image_url,
				'quantity_requested'   => $requested,
				'quantity_reserved'    => $reserved,
				'quantity_available'   => max( 0, $requested - $reserved ),
				'my_reserved_quantity' => 0,
			];
		}

		return [
			'is_sample'     => true,
			'items'         => $items,
			'external_url'  => '',
			'rest_base'     => '',
			'rest_nonce'    => '',
			'requires_name' => false,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_photos( object $product ): array {
		$gallery       = $this->sample_media_urls( $product );
		$example_url   = function_exists( 'home_url' )
			? home_url( '/photos/example/' )
			: 'https://example.test/photos/example/';
		$gallery_items = [];

		foreach ( array_slice( $gallery, 0, 6 ) as $index => $url ) {
			$gallery_items[] = [
				'photo_id'   => $index + 1,
				'image_url'  => $url,
				'caption'    => sprintf(
					/* translators: %d: sample photo number */
					__( 'Guest photo %d', 'prikogstreg-online-invitations' ),
					$index + 1
				),
			];
		}

		return [
			'is_sample'     => true,
			'share_url'     => $example_url,
			'qr_svg'        => $this->qr->svg_for_url( $example_url ),
			'gallery_items' => $gallery_items,
		];
	}

	/**
	 * @return list<string>
	 */
	private function sample_media_urls( object $product ): array {
		$urls = [];

		if ( method_exists( $product, 'get_image_id' ) ) {
			$featured_id = (int) $product->get_image_id();
			if ( $featured_id > 0 && function_exists( 'wp_get_attachment_image_url' ) ) {
				$url = wp_get_attachment_image_url( $featured_id, 'medium' );
				if ( is_string( $url ) && '' !== $url ) {
					$urls[] = $url;
				}
			}
		}

		if ( method_exists( $product, 'get_gallery_image_ids' ) ) {
			foreach ( $product->get_gallery_image_ids() as $attachment_id ) {
				if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
					continue;
				}

				$url = wp_get_attachment_image_url( (int) $attachment_id, 'medium' );
				if ( is_string( $url ) && '' !== $url ) {
					$urls[] = $url;
				}
			}
		}

		$design = EnvelopeDesign::resolve_for_product( $product );
		$design_url = (string) ( $design['image_url'] ?? '' );
		if ( '' !== $design_url ) {
			$urls[] = $design_url;
		}

		return array_values( array_unique( $urls ) );
	}

	/**
	 * @param array<string, mixed> $design
	 */
	private function apply_envelope_design( EnvelopeViewModel $view, array $design ): EnvelopeViewModel {
		$image_url = (string) ( $design['image_url'] ?? '' );
		$image_id  = max( 0, (int) ( $design['image_id'] ?? 0 ) );
		$width     = 0;
		$height    = 0;

		if ( $image_id > 0 && function_exists( 'wp_get_attachment_metadata' ) ) {
			$metadata = wp_get_attachment_metadata( $image_id );
			if ( is_array( $metadata ) ) {
				$width  = max( 0, (int) ( $metadata['width'] ?? 0 ) );
				$height = max( 0, (int) ( $metadata['height'] ?? 0 ) );
			}
		}

		return new EnvelopeViewModel(
			(string) ( $design['preset'] ?: $view->envelope_preset ),
			(string) ( $design['background_preset'] ?: $view->background_preset ),
			'' !== $image_url ? $image_url : $view->envelope_image_url,
			$width,
			$height,
			$view->addressee_label,
			$view->link_type,
			$view->invitation_html,
			$view->invitation_pages,
			$view->page_count,
			$view->poster,
			$view->event_title,
			false,
			$view->sections,
			$this->strip_rest_urls( $view->rsvp_form ),
			$view->wishlist,
			$view->photos,
			$view->event_details,
			'',
			''
		);
	}

	/**
	 * @param array<string, mixed> $rsvp_form
	 * @return array<string, mixed>
	 */
	private function strip_rest_urls( array $rsvp_form ): array {
		$rsvp_form['rest_url']  = '';
		$rsvp_form['rest_nonce'] = '';

		return $rsvp_form;
	}
}
