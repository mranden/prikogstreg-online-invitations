<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin;

use PrikOgStreg\OnlineInvitations\Admin\Invitations\InvitationPreviewController;
use PrikOgStreg\OnlineInvitations\Database\Repositories\DeliveryRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\EventRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\PhotoRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\WishlistItemRepository;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoModerationStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectExpiration;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;
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
		return $this->build_detail( $project_id );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function build_detail( int $project_id ): ?array {
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

		$order_url   = '';
		$order_id    = (int) ( $project['order_id'] ?? 0 );
		$order_label = '';
		if ( $order_id > 0 && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
			if ( is_object( $order ) && function_exists( 'get_edit_post_link' ) ) {
				$order_url   = (string) get_edit_post_link( $order_id, 'raw' );
				$order_label = function_exists( 'wc_get_order_status_name' )
					? (string) wc_get_order_status_name( $order->get_status() )
					: (string) $order->get_status();
			}
		}

		$guest_summary = $this->guests->status_summary( $project_id );
		$photo_counts  = $this->photos->batch_moderation_counts( [ $project_id ] );
		$photo_summary = $photo_counts[ $project_id ] ?? [
			'pending'  => 0,
			'approved' => 0,
			'rejected' => 0,
			'total'    => 0,
		];

		$storage   = $this->redact_storage( $this->storage->diagnose_project( $project ) );
		$failures  = $this->deliveries->list_failures_for_project( $project_id, 10 );
		$published = PublicationStatus::PUBLISHED === (string) ( $project['publication_status'] ?? '' );

		return [
			'project'              => $this->redact_project( $project ),
			'owner_label'          => is_object( $user ) ? (string) ( $user->display_name ?? $user->user_login ?? '' ) : '',
			'owner_email'          => is_object( $user ) ? (string) ( $user->user_email ?? '' ) : '',
			'owner_user_id'        => (int) ( $project['user_id'] ?? 0 ),
			'order_id'             => $order_id,
			'order_item_id'        => (int) ( $project['order_item_id'] ?? 0 ),
			'order_url'            => $order_url,
			'order_status_label'   => $order_label,
			'product_id'           => $product_id,
			'product_name'         => $product_name,
			'effective_expiry'     => ProjectExpiration::effective_expiry( $project ),
			'storage'              => $storage,
			'guest_summary'        => $guest_summary,
			'photo_summary'        => $photo_summary,
			'counts'               => [
				'guests'     => (int) ( $guest_summary['total'] ?? 0 ),
				'wishlist'   => count( $this->wishlist_items->list_for_project( $project_id ) ),
				'photos'     => (int) ( $photo_summary['total'] ?? 0 ),
				'deliveries' => count( $this->deliveries->list_by_project_and_status( $project_id, 'sent' ) )
					+ count( $this->deliveries->list_by_project_and_status( $project_id, 'queued' ) )
					+ count( $this->deliveries->list_failures_for_project( $project_id, 100 ) ),
			],
			'delivery_failures'    => $failures,
			'recent_events'        => $this->events->list_recent_for_project( $project_id, 15 ),
			'my_account_url'       => Endpoints::project_url( $project_id ),
			'retry_import_url'     => ProjectImportRetry::retry_url( $project_id ),
			'is_published'         => $published,
			'has_generic_token'    => '' !== (string) ( $project['generic_token_hash'] ?? '' ),
			'pending_photo_count'  => (int) ( $photo_summary['pending'] ?? 0 ),
			'published_page_count' => max( 0, (int) ( $project['published_version'] ?? 0 ) ),
			'draft_preview_url'    => InvitationPreviewController::preview_url( $project_id, 'draft' ),
			'published_preview_url'=> $published ? InvitationPreviewController::preview_url( $project_id, 'published' ) : '',
		];
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array<string, mixed>
	 */
	private function redact_project( array $project ): array {
		unset(
			$project['generic_token_hash'],
			$project['photo_share_token_hash'],
			$project['photo_access_code_hash'],
			$project['state_manifest_path'],
			$project['published_manifest_path']
		);

		return $project;
	}

	/**
	 * @param array<string, mixed> $storage
	 * @return array<string, mixed>
	 */
	private function redact_storage( array $storage ): array {
		if ( isset( $storage['paths'] ) && is_array( $storage['paths'] ) ) {
			unset( $storage['paths'] );
		}

		return $storage;
	}
}
