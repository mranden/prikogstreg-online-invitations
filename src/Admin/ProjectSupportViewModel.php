<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin;

use PrikOgStreg\OnlineInvitations\Database\Repositories\DeliveryRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\EventRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\PhotoRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\WishlistItemRepository;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectExpiration;
use PrikOgStreg\OnlineInvitations\MyAccount\Endpoints;
use PrikOgStreg\OnlineInvitations\Storage\StorageDiagnostic;

/**
 * Builds safe support dashboard data for a project.
 */
final class ProjectSupportViewModel {

	public function __construct(
		private ProjectRepository $projects,
		private GuestRepository $guests,
		private WishlistItemRepository $wishlist_items,
		private PhotoRepository $photos,
		private DeliveryRepository $deliveries,
		private EventRepository $events,
		private StorageDiagnostic $storage
	) {}

	/**
	 * @return array<string, mixed>|null
	 */
	public function build( int $project_id ): ?array {
		$project = $this->projects->find_by_id( $project_id );
		if ( ! is_array( $project ) ) {
			return null;
		}

		$user = function_exists( 'get_userdata' ) ? get_userdata( (int) ( $project['user_id'] ?? 0 ) ) : false;
		$product_name = '';
		$product_id   = (int) ( $project['product_id'] ?? 0 );
		if ( $product_id > 0 && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( is_object( $product ) && method_exists( $product, 'get_name' ) ) {
				$product_name = (string) $product->get_name();
			}
		}

		$order_url = '';
		$order_id  = (int) ( $project['order_id'] ?? 0 );
		if ( $order_id > 0 && function_exists( 'get_edit_post_link' ) ) {
			$order_url = (string) get_edit_post_link( $order_id, 'raw' );
		}

		$storage = $this->storage->diagnose_project( $project );
		$failures = $this->deliveries->list_failures_for_project( $project_id, 10 );

		return [
			'project'              => $project,
			'owner_label'          => is_object( $user ) ? (string) ( $user->display_name ?? $user->user_login ?? '' ) : '',
			'owner_email'          => is_object( $user ) ? (string) ( $user->user_email ?? '' ) : '',
			'order_id'             => $order_id,
			'order_item_id'        => (int) ( $project['order_item_id'] ?? 0 ),
			'order_url'            => $order_url,
			'product_id'           => $product_id,
			'product_name'         => $product_name,
			'effective_expiry'     => ProjectExpiration::effective_expiry( $project ),
			'storage'              => $storage,
			'counts'               => [
				'guests'    => $this->guests->count_for_project( $project_id, false ),
				'wishlist'  => count( $this->wishlist_items->list_for_project( $project_id ) ),
				'photos'    => count( $this->photos->list_for_project( $project_id ) ),
				'deliveries'=> count( $this->deliveries->list_by_project_and_status( $project_id, 'sent' ) )
					+ count( $this->deliveries->list_by_project_and_status( $project_id, 'queued' ) )
					+ count( $this->deliveries->list_failures_for_project( $project_id, 100 ) ),
			],
			'delivery_failures'    => $failures,
			'recent_events'        => $this->events->list_recent_for_project( $project_id, 15 ),
			'my_account_url'       => Endpoints::project_url( $project_id ),
			'retry_import_url'     => ProjectImportRetry::retry_url( $project_id ),
		];
	}
}
