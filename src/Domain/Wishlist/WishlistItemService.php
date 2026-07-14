<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Wishlist;

use PrikOgStreg\OnlineInvitations\Database\Repositories\EventRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\WishlistItemRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\WishlistReservationRepository;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectEntitlement;

/**
 * Owner wishlist item CRUD, reorder, and project wishlist settings.
 */
final class WishlistItemService {

	public function __construct(
		private WishlistItemRepository $items,
		private WishlistReservationRepository $reservations,
		private ProjectRepository $projects,
		private GuestRepository $guests,
		private EventRepository $events
	) {}

	/**
	 * @param array<string, mixed> $project
	 * @return list<array<string, mixed>>
	 */
	public function list_for_owner( array $project ): array {
		$rows = $this->items->list_for_project(
			(int) $project['project_id'],
			WishlistItemStatus::owner_visible()
		);

		return array_map( fn( array $row ): array => $this->format_owner_item( $row, $project ), $rows );
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $input
	 * @return array{success:bool,error?:string,item_id?:int}
	 */
	public function save_item( array $project, array $input ): array {
		if ( ! ProjectEntitlement::can_edit_project( $project ) ) {
			return [ 'success' => false, 'error' => 'entitlement_denied' ];
		}

		$title = WishlistSanitizer::title( (string) ( $input['title'] ?? '' ) );
		if ( '' === $title ) {
			return [ 'success' => false, 'error' => 'missing_title' ];
		}

		$external_url = WishlistSanitizer::external_url( (string) ( $input['external_url'] ?? '' ) );
		if ( null === $external_url && '' !== trim( (string) ( $input['external_url'] ?? '' ) ) ) {
			return [ 'success' => false, 'error' => 'invalid_url' ];
		}

		$image_url = WishlistSanitizer::image_url( (string) ( $input['image_url'] ?? '' ) );
		if ( null === $image_url && '' !== trim( (string) ( $input['image_url'] ?? '' ) ) ) {
			return [ 'success' => false, 'error' => 'invalid_image_url' ];
		}

		$data = [
			'title'              => $title,
			'description'        => WishlistSanitizer::description( (string) ( $input['description'] ?? '' ) ),
			'external_url'       => $external_url,
			'image_path'         => $image_url,
			'quantity_requested' => WishlistSanitizer::quantity( $input['quantity_requested'] ?? 1 ),
			'sort_order'         => WishlistSanitizer::sort_order( $input['sort_order'] ?? 0 ),
			'status'             => WishlistSanitizer::status( (string) ( $input['status'] ?? WishlistItemStatus::ACTIVE ) ),
		];

		$item_id = (int) ( $input['wishlist_item_id'] ?? 0 );
		if ( $item_id > 0 ) {
			$existing = $this->items->find_by_id_for_project( $item_id, (int) $project['project_id'] );
			if ( ! is_array( $existing ) ) {
				return [ 'success' => false, 'error' => 'item_missing' ];
			}

			$reserved = (int) ( $existing['quantity_reserved'] ?? 0 );
			if ( $data['quantity_requested'] < $reserved ) {
				return [ 'success' => false, 'error' => 'quantity_below_reserved' ];
			}

			$this->items->update( $item_id, $data );
			$this->record_event( (int) $project['project_id'], $item_id, 'wishlist_item_updated' );

			return [ 'success' => true, 'item_id' => $item_id ];
		}

		$data['project_id']       = (int) $project['project_id'];
		$data['quantity_reserved'] = 0;
		$item_id                  = $this->items->insert( $data );
		if ( $item_id <= 0 ) {
			return [ 'success' => false, 'error' => 'save_failed' ];
		}

		$this->record_event( (int) $project['project_id'], $item_id, 'wishlist_item_created' );

		return [ 'success' => true, 'item_id' => $item_id ];
	}

	/**
	 * @param array<string, mixed> $project
	 * @param list<int>            $ordered_ids
	 * @return array{success:bool,error?:string}
	 */
	public function reorder_items( array $project, array $ordered_ids ): array {
		if ( ! ProjectEntitlement::can_edit_project( $project ) ) {
			return [ 'success' => false, 'error' => 'entitlement_denied' ];
		}

		$project_id = (int) $project['project_id'];
		$sort       = 0;
		foreach ( $ordered_ids as $item_id ) {
			$item_id = (int) $item_id;
			if ( $item_id <= 0 ) {
				continue;
			}

			$row = $this->items->find_by_id_for_project( $item_id, $project_id );
			if ( ! is_array( $row ) ) {
				continue;
			}

			$this->items->update( $item_id, [ 'sort_order' => $sort ] );
			++$sort;
		}

		$this->record_event( $project_id, 0, 'wishlist_items_reordered' );

		return [ 'success' => true ];
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $input
	 * @return array{success:bool,error?:string}
	 */
	public function save_settings( array $project, array $input ): array {
		if ( ! ProjectEntitlement::can_edit_project( $project ) ) {
			return [ 'success' => false, 'error' => 'entitlement_denied' ];
		}

		$external = WishlistSanitizer::external_url( (string) ( $input['external_wishlist_url'] ?? '' ) );
		if ( null === $external && '' !== trim( (string) ( $input['external_wishlist_url'] ?? '' ) ) ) {
			return [ 'success' => false, 'error' => 'invalid_external_wishlist_url' ];
		}

		$this->projects->update(
			(int) $project['project_id'],
			[
				'external_wishlist_url'     => $external,
				'internal_wishlist_enabled' => ! empty( $input['internal_wishlist_enabled'] ) ? 1 : 0,
				'show_reserver_identity'    => ! empty( $input['show_reserver_identity'] ) ? 1 : 0,
			]
		);

		$this->record_event( (int) $project['project_id'], 0, 'wishlist_settings_saved' );

		return [ 'success' => true ];
	}

	/**
	 * @param array<string, mixed> $row
	 * @param array<string, mixed> $project
	 * @return array<string, mixed>
	 */
	private function format_owner_item( array $row, array $project ): array {
		$item_id = (int) ( $row['wishlist_item_id'] ?? 0 );
		$reservations = $this->reservations->list_active_for_item( $item_id );
		$reservers    = [];

		if ( ! empty( $project['show_reserver_identity'] ) ) {
			foreach ( $reservations as $reservation ) {
				$guest = $this->guests->find_by_id( (int) ( $reservation['guest_id'] ?? 0 ) );
				$reservers[] = [
					'guest_id'     => (int) ( $reservation['guest_id'] ?? 0 ),
					'display_name' => is_array( $guest ) ? (string) ( $guest['display_name'] ?? '' ) : '',
					'quantity'     => (int) ( $reservation['quantity'] ?? 0 ),
				];
			}
		}

		return [
			'wishlist_item_id'   => $item_id,
			'title'              => (string) ( $row['title'] ?? '' ),
			'description'        => (string) ( $row['description'] ?? '' ),
			'external_url'       => (string) ( $row['external_url'] ?? '' ),
			'image_url'          => (string) ( $row['image_path'] ?? '' ),
			'quantity_requested' => (int) ( $row['quantity_requested'] ?? 0 ),
			'quantity_reserved'  => (int) ( $row['quantity_reserved'] ?? 0 ),
			'quantity_available' => max( 0, (int) ( $row['quantity_requested'] ?? 0 ) - (int) ( $row['quantity_reserved'] ?? 0 ) ),
			'sort_order'         => (int) ( $row['sort_order'] ?? 0 ),
			'status'             => (string) ( $row['status'] ?? WishlistItemStatus::ACTIVE ),
			'reservers'          => $reservers,
		];
	}

	private function record_event( int $project_id, int $item_id, string $event_type ): void {
		$this->events->insert(
			[
				'project_id'    => $project_id,
				'actor_type'    => 'customer',
				'event_type'    => $event_type,
				'metadata_json' => wp_json_encode( [ 'wishlist_item_id' => $item_id ], JSON_UNESCAPED_SLASHES ) ?: '{}',
			]
		);
	}
}
