<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Wishlist;

use PrikOgStreg\OnlineInvitations\Database\Repositories\EventRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\WishlistItemRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\WishlistReservationRepository;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestSendTokenStore;
use PrikOgStreg\OnlineInvitations\Domain\Guest\InvitationStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicEntitlement;
use PrikOgStreg\OnlineInvitations\Public\TokenResolution;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

/**
 * Atomic wishlist reserve/release for public guests.
 */
final class WishlistReservationService {

	public function __construct(
		private WishlistItemRepository $items,
		private WishlistReservationRepository $reservations,
		private GuestRepository $guests,
		private EventRepository $events
	) {}

	/**
	 * @param array<string, mixed> $input
	 * @return array{success:bool,error?:string,replayed?:bool,item?:array<string,mixed>,guest_id?:int}
	 */
	public function reserve( TokenResolution $resolution, int $wishlist_item_id, array $input, string $idempotency_key ): array {
		$project = $resolution->project();
		if ( ! PublicEntitlement::is_publicly_available( $project ) ) {
			return $this->error( 'unavailable' );
		}

		if ( empty( $project['internal_wishlist_enabled'] ) ) {
			return $this->error( 'wishlist_disabled' );
		}

		$guest = $this->resolve_guest( $resolution, $input, $idempotency_key );
		if ( isset( $guest['error'] ) ) {
			return $this->error( (string) $guest['error'] );
		}

		/** @var array<string, mixed> $guest_row */
		$guest_row = $guest['guest'];
		$guest_id  = (int) ( $guest_row['guest_id'] ?? 0 );

		$item = $this->items->find_by_id_for_project( $wishlist_item_id, (int) $project['project_id'] );
		if ( ! is_array( $item ) || WishlistItemStatus::ACTIVE !== (string) ( $item['status'] ?? '' ) ) {
			return $this->error( 'item_unavailable' );
		}

		$quantity = WishlistSanitizer::quantity( $input['quantity'] ?? 1 );
		$signature = $this->reservation_signature( $wishlist_item_id, $guest_id, $quantity );

		if ( $this->is_replay( $idempotency_key, $signature ) ) {
			return [
				'success'  => true,
				'replayed' => true,
				'item'     => $this->format_public_item( $item, $guest_id ),
				'guest_id' => $guest_id,
			];
		}

		$existing = $this->reservations->find_active_for_guest_item( $wishlist_item_id, $guest_id );
		if ( is_array( $existing ) && (int) ( $existing['quantity'] ?? 0 ) === $quantity ) {
			$this->remember_idempotency( $idempotency_key, $signature );

			return [
				'success'  => true,
				'replayed' => true,
				'item'     => $this->format_public_item( $this->items->find_by_id( $wishlist_item_id ) ?? $item, $guest_id ),
				'guest_id' => $guest_id,
			];
		}

		$result = $this->apply_reserve_change( $item, $guest_id, $quantity, is_array( $existing ) ? $existing : null );
		if ( ! $result['success'] ) {
			return $result;
		}

		$this->record_event(
			$project,
			$guest_id,
			$wishlist_item_id,
			'wishlist_reserved',
			[ 'quantity' => $quantity ]
		);
		$this->remember_idempotency( $idempotency_key, $signature );

		$fresh = $this->items->find_by_id( $wishlist_item_id );

		return [
			'success'  => true,
			'item'     => $this->format_public_item( is_array( $fresh ) ? $fresh : $item, $guest_id ),
			'guest_id' => $guest_id,
		];
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array{success:bool,error?:string,replayed?:bool,item?:array<string,mixed>}
	 */
	public function release( TokenResolution $resolution, int $wishlist_item_id, array $input, string $idempotency_key ): array {
		$project = $resolution->project();
		if ( ! PublicEntitlement::is_publicly_available( $project ) ) {
			return $this->error( 'unavailable' );
		}

		$guest = $this->resolve_guest( $resolution, $input, $idempotency_key );
		if ( isset( $guest['error'] ) ) {
			return $this->error( (string) $guest['error'] );
		}

		/** @var array<string, mixed> $guest_row */
		$guest_row = $guest['guest'];
		$guest_id  = (int) ( $guest_row['guest_id'] ?? 0 );

		$item = $this->items->find_by_id_for_project( $wishlist_item_id, (int) $project['project_id'] );
		if ( ! is_array( $item ) ) {
			return $this->error( 'item_unavailable' );
		}

		$signature = $this->release_signature( $wishlist_item_id, $guest_id );
		if ( $this->is_replay( $idempotency_key, $signature ) ) {
			return [
				'success'  => true,
				'replayed' => true,
				'item'     => $this->format_public_item( $item, $guest_id ),
			];
		}

		$existing = $this->reservations->find_active_for_guest_item( $wishlist_item_id, $guest_id );
		if ( ! is_array( $existing ) ) {
			$this->remember_idempotency( $idempotency_key, $signature );

			return [
				'success'  => true,
				'replayed' => true,
				'item'     => $this->format_public_item( $item, $guest_id ),
			];
		}

		$result = $this->apply_release( $item, $existing );
		if ( ! $result['success'] ) {
			return $result;
		}

		$this->record_event(
			$project,
			$guest_id,
			$wishlist_item_id,
			'wishlist_released',
			[ 'quantity' => (int) ( $existing['quantity'] ?? 0 ) ]
		);
		$this->remember_idempotency( $idempotency_key, $signature );

		$fresh = $this->items->find_by_id( $wishlist_item_id );

		return [
			'success' => true,
			'item'    => $this->format_public_item( is_array( $fresh ) ? $fresh : $item, $guest_id ),
		];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_public_items( TokenResolution $resolution ): array {
		$project = $resolution->project();
		if ( ! PublicEntitlement::is_publicly_available( $project ) || empty( $project['internal_wishlist_enabled'] ) ) {
			return [];
		}

		$guest_id = 0;
		$guest    = $resolution->guest();
		if ( is_array( $guest ) ) {
			$guest_id = (int) ( $guest['guest_id'] ?? 0 );
		}

		$rows = $this->items->list_for_project( (int) $project['project_id'], [ WishlistItemStatus::ACTIVE ] );

		return array_map(
			fn( array $row ): array => $this->format_public_item( $row, $guest_id ),
			$rows
		);
	}

	/**
	 * @param array<string, mixed>      $input
	 * @return array{guest:array<string,mixed>}|array{error:string}
	 */
	private function resolve_guest( TokenResolution $resolution, array $input, string $idempotency_key ): array {
		$guest = $resolution->guest();
		if ( $resolution->is_personal() ) {
			if ( ! is_array( $guest ) || ! PublicEntitlement::is_guest_accessible( $guest ) ) {
				return [ 'error' => 'invalid_context' ];
			}

			return [ 'guest' => $guest ];
		}

		$replay_guest_id = $this->replay_guest_id_for_key( $idempotency_key );
		if ( $replay_guest_id > 0 ) {
			$existing = $this->guests->find_by_id( $replay_guest_id );
			if ( is_array( $existing ) && (int) ( $existing['project_id'] ?? 0 ) === (int) $resolution->project()['project_id'] ) {
				return [ 'guest' => $existing ];
			}
		}

		$name = WishlistSanitizer::display_name( (string) ( $input['display_name'] ?? '' ) );
		if ( '' === $name ) {
			return [ 'error' => 'missing_display_name' ];
		}

		$pair = InvitationToken::generate();
		$guest_id = $this->guests->insert(
			[
				'project_id'          => (int) $resolution->project()['project_id'],
				'display_name'        => $name,
				'email'               => null,
				'token_hash'          => $pair['hash'],
				'token_version'       => 1,
				'invitation_status'   => InvitationStatus::OPENED,
				'is_generic_response' => 1,
			]
		);

		if ( $guest_id <= 0 ) {
			return [ 'error' => 'create_failed' ];
		}

		GuestSendTokenStore::remember( $guest_id, $pair['raw'] );
		$created = $this->guests->find_by_id( $guest_id );
		if ( ! is_array( $created ) ) {
			return [ 'error' => 'create_failed' ];
		}

		$this->remember_guest_for_key( $idempotency_key, $guest_id );

		return [ 'guest' => $created ];
	}

	/**
	 * @param array<string, mixed>      $item
	 * @param array<string, mixed>|null $existing
	 * @return array{success:bool,error?:string}
	 */
	private function apply_reserve_change( array $item, int $guest_id, int $quantity, ?array $existing ): array {
		$item_id          = (int) ( $item['wishlist_item_id'] ?? 0 );
		$current_reserved = (int) ( $item['quantity_reserved'] ?? 0 );
		$requested        = (int) ( $item['quantity_requested'] ?? 0 );
		$old_quantity     = is_array( $existing ) ? (int) ( $existing['quantity'] ?? 0 ) : 0;
		$delta            = $quantity - $old_quantity;
		$new_total        = $current_reserved + $delta;

		if ( $delta > 0 && $new_total > $requested ) {
			return $this->error( 'insufficient_quantity' );
		}

		if ( ! $this->items->try_adjust_reserved( $item_id, $delta, $current_reserved ) ) {
			return $this->error( 'insufficient_quantity' );
		}

		if ( is_array( $existing ) ) {
			$ok = $this->reservations->update(
				(int) $existing['reservation_id'],
				[ 'quantity' => $quantity ]
			);
		} elseif ( $quantity > 0 ) {
			$reservation_id = $this->reservations->insert(
				[
					'wishlist_item_id' => $item_id,
					'project_id'       => (int) ( $item['project_id'] ?? 0 ),
					'guest_id'         => $guest_id,
					'quantity'         => $quantity,
					'status'           => WishlistReservationStatus::ACTIVE,
				]
			);
			$ok = $reservation_id > 0;
		} else {
			$ok = true;
		}

		if ( ! $ok ) {
			$this->items->try_adjust_reserved( $item_id, -$delta, $current_reserved + $delta );

			return $this->error( 'save_failed' );
		}

		return [ 'success' => true ];
	}

	/**
	 * @param array<string, mixed> $item
	 * @param array<string, mixed> $existing
	 * @return array{success:bool,error?:string}
	 */
	private function apply_release( array $item, array $existing ): array {
		$item_id          = (int) ( $item['wishlist_item_id'] ?? 0 );
		$current_reserved = (int) ( $item['quantity_reserved'] ?? 0 );
		$quantity         = (int) ( $existing['quantity'] ?? 0 );

		if ( ! $this->items->try_adjust_reserved( $item_id, -$quantity, $current_reserved ) ) {
			return $this->error( 'save_failed' );
		}

		$ok = $this->reservations->update(
			(int) $existing['reservation_id'],
			[
				'status'          => WishlistReservationStatus::RELEASED,
				'released_at_utc' => UtcDateTime::now(),
			]
		);

		if ( ! $ok ) {
			$this->items->try_adjust_reserved( $item_id, $quantity, $current_reserved - $quantity );

			return $this->error( 'save_failed' );
		}

		return [ 'success' => true ];
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function format_public_item( array $row, int $guest_id ): array {
		$item_id = (int) ( $row['wishlist_item_id'] ?? 0 );
		$mine    = 0;
		if ( $guest_id > 0 ) {
			$reservation = $this->reservations->find_active_for_guest_item( $item_id, $guest_id );
			if ( is_array( $reservation ) ) {
				$mine = (int) ( $reservation['quantity'] ?? 0 );
			}
		}

		$requested = (int) ( $row['quantity_requested'] ?? 0 );
		$reserved  = (int) ( $row['quantity_reserved'] ?? 0 );

		return [
			'wishlist_item_id'   => $item_id,
			'title'              => (string) ( $row['title'] ?? '' ),
			'description'        => (string) ( $row['description'] ?? '' ),
			'external_url'       => (string) ( $row['external_url'] ?? '' ),
			'image_url'          => (string) ( $row['image_path'] ?? '' ),
			'quantity_requested' => $requested,
			'quantity_reserved'  => $reserved,
			'quantity_available' => max( 0, $requested - $reserved ),
			'my_reserved_quantity' => $mine,
		];
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $metadata
	 */
	private function record_event( array $project, int $guest_id, int $item_id, string $event_type, array $metadata ): void {
		$metadata['wishlist_item_id'] = $item_id;
		$encoded = wp_json_encode( $metadata, JSON_UNESCAPED_SLASHES );

		$this->events->insert(
			[
				'project_id'    => (int) $project['project_id'],
				'guest_id'      => $guest_id,
				'actor_type'    => 'guest',
				'event_type'    => $event_type,
				'metadata_json' => is_string( $encoded ) ? $encoded : '{}',
			]
		);
	}

	private function reservation_signature( int $item_id, int $guest_id, int $quantity ): string {
		return hash( 'sha256', 'reserve:' . $item_id . ':' . $guest_id . ':' . $quantity );
	}

	private function release_signature( int $item_id, int $guest_id ): string {
		return hash( 'sha256', 'release:' . $item_id . ':' . $guest_id );
	}

	private function is_replay( string $idempotency_key, string $signature ): bool {
		$stored = $this->load_idempotency( $idempotency_key );

		return is_array( $stored ) && (string) ( $stored['signature'] ?? '' ) === $signature;
	}

	private function remember_idempotency( string $idempotency_key, string $signature ): void {
		$key = $this->idempotency_transient_key( $idempotency_key );
		if ( '' === $key ) {
			return;
		}

		set_transient( $key, [ 'signature' => $signature ], DAY_IN_SECONDS );
	}

	private function remember_guest_for_key( string $idempotency_key, int $guest_id ): void {
		$key = $this->guest_transient_key( $idempotency_key );
		if ( '' === $key ) {
			return;
		}

		set_transient( $key, $guest_id, DAY_IN_SECONDS );
	}

	private function replay_guest_id_for_key( string $idempotency_key ): int {
		$key = $this->guest_transient_key( $idempotency_key );
		if ( '' === $key ) {
			return 0;
		}

		$value = get_transient( $key );

		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * @return array{signature:string}|null
	 */
	private function load_idempotency( string $idempotency_key ): ?array {
		$key = $this->idempotency_transient_key( $idempotency_key );
		if ( '' === $key ) {
			return null;
		}

		$stored = get_transient( $key );

		return is_array( $stored ) ? $stored : null;
	}

	private function idempotency_transient_key( string $idempotency_key ): string {
		$idempotency_key = trim( $idempotency_key );
		if ( '' === $idempotency_key || strlen( $idempotency_key ) > 128 ) {
			return '';
		}

		return 'pks_oi_wishlist_idem_' . hash( 'sha256', $idempotency_key );
	}

	private function guest_transient_key( string $idempotency_key ): string {
		$idempotency_key = trim( $idempotency_key );
		if ( '' === $idempotency_key || strlen( $idempotency_key ) > 128 ) {
			return '';
		}

		return 'pks_oi_wishlist_guest_' . hash( 'sha256', $idempotency_key );
	}

	/**
	 * @return array{success:false,error:string}
	 */
	private function error( string $code ): array {
		return [ 'success' => false, 'error' => $code ];
	}
}
