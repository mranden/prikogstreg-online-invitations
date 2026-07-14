<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Privacy;

use PrikOgStreg\OnlineInvitations\Database\Repositories\AddressBookRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\DeliveryRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\EventRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\PhotoRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\WishlistItemRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\WishlistReservationRepository;

/**
 * Builds WordPress personal data export payloads for owners and guests.
 */
final class PersonalDataExporter {

	public function __construct(
		private ProjectRepository $projects,
		private GuestRepository $guests,
		private AddressBookRepository $address_book,
		private WishlistItemRepository $wishlist_items,
		private WishlistReservationRepository $wishlist_reservations,
		private PhotoRepository $photos,
		private DeliveryRepository $deliveries,
		private EventRepository $events
	) {}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function export_for_email( string $email, int $page = 1 ): array {
		$email = strtolower( trim( $email ) );
		if ( '' === $email ) {
			return [];
		}

		$user_id = $this->resolve_user_id( $email );
		$items   = [];

		if ( $user_id > 0 ) {
			$items = array_merge( $items, $this->export_owner_data( $user_id ) );
		}

		$items = array_merge( $items, $this->export_guest_data( $email, $user_id ) );

		return $items;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function export_owner_data( int $user_id ): array {
		$items    = [];
		$projects = $this->projects->list_for_user( $user_id );

		foreach ( $projects as $project ) {
			$project_id = (int) ( $project['project_id'] ?? 0 );
			$items[]    = [
				'group_id'    => 'pks-oi-projects',
				'group_label' => __( 'Online invitation projects', 'prikogstreg-online-invitations' ),
				'item_id'     => 'project-' . $project_id,
				'data'        => ExportRedaction::project_fields( $project ),
			];
		}

		foreach ( $this->address_book->list_active_for_user( $user_id ) as $contact ) {
			$items[] = [
				'group_id'    => 'pks-oi-address-book',
				'group_label' => __( 'Invitation address book', 'prikogstreg-online-invitations' ),
				'item_id'     => 'address-book-' . (int) ( $contact['address_book_id'] ?? 0 ),
				'data'        => ExportRedaction::address_book_fields( $contact ),
			];
		}

		foreach ( $projects as $project ) {
			$project_id = (int) ( $project['project_id'] ?? 0 );
			foreach ( $this->guests->list_by_project( $project_id ) as $guest ) {
				$items[] = [
					'group_id'    => 'pks-oi-guests',
					'group_label' => __( 'Project guests (owner export)', 'prikogstreg-online-invitations' ),
					'item_id'     => 'guest-' . (int) ( $guest['guest_id'] ?? 0 ),
					'data'        => ExportRedaction::guest_fields( $guest ),
				];
			}

			foreach ( $this->wishlist_items->list_for_project( $project_id ) as $item ) {
				$items[] = [
					'group_id'    => 'pks-oi-wishlist',
					'group_label' => __( 'Wishlist items', 'prikogstreg-online-invitations' ),
					'item_id'     => 'wishlist-item-' . (int) ( $item['wishlist_item_id'] ?? 0 ),
					'data'        => ExportRedaction::scalar_fields_public( $item ),
				];
			}

			foreach ( $this->photos->list_for_project( $project_id, null, true ) as $photo ) {
				$items[] = [
					'group_id'    => 'pks-oi-photos',
					'group_label' => __( 'Guest photo metadata', 'prikogstreg-online-invitations' ),
					'item_id'     => 'photo-' . (int) ( $photo['photo_id'] ?? 0 ),
					'data'        => ExportRedaction::photo_fields( $photo ),
				];
			}

			foreach ( $this->deliveries->list_by_project( $project_id ) as $delivery ) {
				$items[] = [
					'group_id'    => 'pks-oi-deliveries',
					'group_label' => __( 'Invitation delivery history', 'prikogstreg-online-invitations' ),
					'item_id'     => 'delivery-' . (int) ( $delivery['delivery_id'] ?? 0 ),
					'data'        => ExportRedaction::delivery_fields( $delivery ),
				];
			}

			foreach ( $this->events->list_recent_for_project( $project_id, 100 ) as $event ) {
				$items[] = [
					'group_id'    => 'pks-oi-events',
					'group_label' => __( 'Project activity log', 'prikogstreg-online-invitations' ),
					'item_id'     => 'event-' . (int) ( $event['event_id'] ?? 0 ),
					'data'        => ExportRedaction::event_fields( $event ),
				];
			}
		}

		return $items;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function export_guest_data( string $email, int $owner_user_id ): array {
		$items = [];
		foreach ( $this->guests->list_by_email( $email ) as $guest ) {
			$project_id = (int) ( $guest['project_id'] ?? 0 );
			$project    = $this->projects->find_by_id( $project_id );
			if ( ! is_array( $project ) ) {
				continue;
			}

			if ( $owner_user_id > 0 && (int) ( $project['user_id'] ?? 0 ) === $owner_user_id ) {
				continue;
			}

			$guest_id = (int) ( $guest['guest_id'] ?? 0 );
			$items[]  = [
				'group_id'    => 'pks-oi-guest-self',
				'group_label' => __( 'Your guest invitation data', 'prikogstreg-online-invitations' ),
				'item_id'     => 'guest-self-' . $guest_id,
				'data'        => ExportRedaction::guest_fields( $guest ),
			];

			foreach ( $this->wishlist_reservations->list_for_guest( $guest_id ) as $reservation ) {
				$items[] = [
					'group_id'    => 'pks-oi-wishlist-reservations',
					'group_label' => __( 'Your wishlist reservations', 'prikogstreg-online-invitations' ),
					'item_id'     => 'reservation-' . (int) ( $reservation['reservation_id'] ?? 0 ),
					'data'        => ExportRedaction::scalar_fields_public( $reservation ),
				];
			}

			foreach ( $this->photos->list_for_project( $project_id, null, true ) as $photo ) {
				if ( (int) ( $photo['guest_id'] ?? 0 ) !== $guest_id ) {
					continue;
				}
				$items[] = [
					'group_id'    => 'pks-oi-guest-photos',
					'group_label' => __( 'Your uploaded photos', 'prikogstreg-online-invitations' ),
					'item_id'     => 'guest-photo-' . (int) ( $photo['photo_id'] ?? 0 ),
					'data'        => ExportRedaction::photo_fields( $photo ),
				];
			}
		}

		return $items;
	}

	private function resolve_user_id( string $email ): int {
		if ( ! function_exists( 'get_user_by' ) ) {
			return 0;
		}

		$user = get_user_by( 'email', $email );
		if ( ! is_object( $user ) || ! isset( $user->ID ) ) {
			return 0;
		}

		return (int) $user->ID;
	}
}
