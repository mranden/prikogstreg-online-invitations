<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\MyAccount;

use PrikOgStreg\OnlineInvitations\Domain\Wishlist\WishlistItemService;
use PrikOgStreg\OnlineInvitations\Security\Authorization;
use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;

/**
 * My Account wishlist management for project owners.
 */
final class WishlistController {

	public function __construct(
		private WishlistItemService $wishlist,
		private Authorization $authorization,
		private TemplateLoader $templates
	) {}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $context
	 */
	public function render( array $project, array $context ): void {
		$items = $this->wishlist->list_for_owner( $project );
		$edit_item = null;
		$edit_id   = isset( $_GET['pks_oi_edit_item'] ) ? (int) $_GET['pks_oi_edit_item'] : 0;
		if ( $edit_id > 0 ) {
			foreach ( $items as $item ) {
				if ( (int) ( $item['wishlist_item_id'] ?? 0 ) === $edit_id ) {
					$edit_item = $item;
					break;
				}
			}
		}

		$this->templates->render(
			'myaccount/project-wishlist',
			array_merge(
				$context,
				[
					'wishlist_items'        => $items,
					'edit_item'             => $edit_item,
					'external_wishlist_url' => (string) ( $project['external_wishlist_url'] ?? '' ),
					'internal_enabled'      => ! empty( $project['internal_wishlist_enabled'] ),
					'show_reserver_identity'=> ! empty( $project['show_reserver_identity'] ),
				]
			)
		);
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public function handle_post( array $project, string $section, string $redirect_url ): bool {
		if ( ProjectSections::WISHLIST !== $section ) {
			return false;
		}

		$action = sanitize_key( (string) ( $_POST['pks_oi_action'] ?? '' ) );

		if ( 'save_wishlist_settings' === $action ) {
			$this->wishlist->save_settings( $project, wp_unslash( $_POST ) );
			wp_safe_redirect( add_query_arg( 'pks_oi_saved', '1', $redirect_url ) );
			exit;
		}

		if ( 'save_wishlist_item' === $action ) {
			$result = $this->wishlist->save_item( $project, wp_unslash( $_POST ) );
			$args   = [ 'pks_oi_saved' => '1' ];
			if ( ! empty( $result['item_id'] ) ) {
				$args['pks_oi_edit_item'] = (int) $result['item_id'];
			}
			wp_safe_redirect( add_query_arg( $args, $redirect_url ) );
			exit;
		}

		if ( 'reorder_wishlist' === $action ) {
			$ids = array_map( 'intval', (array) ( $_POST['wishlist_item_ids'] ?? [] ) );
			$this->wishlist->reorder_items( $project, $ids );
			wp_safe_redirect( add_query_arg( 'pks_oi_saved', '1', $redirect_url ) );
			exit;
		}

		return false;
	}
}
