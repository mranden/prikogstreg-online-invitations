<?php
/**
 * Owner wishlist management.
 *
 * @package PrikOgStreg\OnlineInvitations
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/_helpers.php';

use PrikOgStreg\OnlineInvitations\Domain\Wishlist\WishlistItemStatus;
use PrikOgStreg\OnlineInvitations\MyAccount\ProjectController;

?>
<div class="pks-oi pks-oi-myaccount pks-oi-project">
	<?php pks_oi_render_notices( $notices ); ?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<section aria-labelledby="pks-oi-wishlist-title">
		<h3 id="pks-oi-wishlist-title"><?php esc_html_e( 'Wishlist', 'prikogstreg-online-invitations' ); ?></h3>

		<?php if ( $can_edit ) : ?>
			<form method="post" class="pks-oi-form pks-oi-wishlist-settings">
				<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
				<input type="hidden" name="pks_oi_action" value="save_wishlist_settings" />
				<p>
					<label for="pks-oi-external-wishlist"><?php esc_html_e( 'External Ønskeskyen URL (optional)', 'prikogstreg-online-invitations' ); ?></label><br />
					<input type="url" id="pks-oi-external-wishlist" name="external_wishlist_url" value="<?php echo esc_attr( $external_wishlist_url ); ?>" class="large-text" placeholder="https://" />
				</p>
				<p>
					<label><input type="checkbox" name="internal_wishlist_enabled" value="1" <?php checked( $internal_enabled ); ?> /> <?php esc_html_e( 'Enable internal wishlist', 'prikogstreg-online-invitations' ); ?></label>
				</p>
				<p>
					<label><input type="checkbox" name="show_reserver_identity" value="1" <?php checked( $show_reserver_identity ); ?> /> <?php esc_html_e( 'Show who reserved each gift (off preserves surprise)', 'prikogstreg-online-invitations' ); ?></label>
				</p>
				<p><button type="submit" class="button"><?php esc_html_e( 'Save wishlist settings', 'prikogstreg-online-invitations' ); ?></button></p>
			</form>

			<h4><?php echo is_array( $edit_item ) ? esc_html__( 'Edit item', 'prikogstreg-online-invitations' ) : esc_html__( 'Add item', 'prikogstreg-online-invitations' ); ?></h4>
			<form method="post" class="pks-oi-form">
				<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
				<input type="hidden" name="pks_oi_action" value="save_wishlist_item" />
				<input type="hidden" name="wishlist_item_id" value="<?php echo esc_attr( (string) ( $edit_item['wishlist_item_id'] ?? 0 ) ); ?>" />
				<p>
					<label for="pks-oi-wishlist-title-field"><?php esc_html_e( 'Title', 'prikogstreg-online-invitations' ); ?></label><br />
					<input type="text" id="pks-oi-wishlist-title-field" name="title" required value="<?php echo esc_attr( (string) ( $edit_item['title'] ?? '' ) ); ?>" />
				</p>
				<p>
					<label for="pks-oi-wishlist-description"><?php esc_html_e( 'Description', 'prikogstreg-online-invitations' ); ?></label><br />
					<textarea id="pks-oi-wishlist-description" name="description" rows="3"><?php echo esc_textarea( (string) ( $edit_item['description'] ?? '' ) ); ?></textarea>
				</p>
				<p>
					<label for="pks-oi-wishlist-url"><?php esc_html_e( 'Product URL (optional)', 'prikogstreg-online-invitations' ); ?></label><br />
					<input type="url" id="pks-oi-wishlist-url" name="external_url" value="<?php echo esc_attr( (string) ( $edit_item['external_url'] ?? '' ) ); ?>" />
				</p>
				<p>
					<label for="pks-oi-wishlist-image"><?php esc_html_e( 'Image URL (optional)', 'prikogstreg-online-invitations' ); ?></label><br />
					<input type="url" id="pks-oi-wishlist-image" name="image_url" value="<?php echo esc_attr( (string) ( $edit_item['image_url'] ?? '' ) ); ?>" />
				</p>
				<p>
					<label for="pks-oi-wishlist-quantity"><?php esc_html_e( 'Quantity requested', 'prikogstreg-online-invitations' ); ?></label><br />
					<input type="number" id="pks-oi-wishlist-quantity" name="quantity_requested" min="1" max="99" value="<?php echo esc_attr( (string) ( $edit_item['quantity_requested'] ?? 1 ) ); ?>" />
				</p>
				<p>
					<label for="pks-oi-wishlist-status"><?php esc_html_e( 'Visibility', 'prikogstreg-online-invitations' ); ?></label><br />
					<select id="pks-oi-wishlist-status" name="status">
						<option value="<?php echo esc_attr( WishlistItemStatus::ACTIVE ); ?>" <?php selected( (string) ( $edit_item['status'] ?? WishlistItemStatus::ACTIVE ), WishlistItemStatus::ACTIVE ); ?>><?php esc_html_e( 'Active', 'prikogstreg-online-invitations' ); ?></option>
						<option value="<?php echo esc_attr( WishlistItemStatus::HIDDEN ); ?>" <?php selected( (string) ( $edit_item['status'] ?? '' ), WishlistItemStatus::HIDDEN ); ?>><?php esc_html_e( 'Hidden', 'prikogstreg-online-invitations' ); ?></option>
					</select>
				</p>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save item', 'prikogstreg-online-invitations' ); ?></button></p>
			</form>
		<?php endif; ?>

		<form method="post" class="pks-oi-wishlist-list">
			<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
			<table class="shop_table shop_table_responsive pks-oi-wishlist-table">
				<thead>
					<tr>
						<?php if ( $can_edit ) : ?><th><?php esc_html_e( 'Order', 'prikogstreg-online-invitations' ); ?></th><?php endif; ?>
						<th><?php esc_html_e( 'Title', 'prikogstreg-online-invitations' ); ?></th>
						<th><?php esc_html_e( 'Reserved', 'prikogstreg-online-invitations' ); ?></th>
						<th><?php esc_html_e( 'Status', 'prikogstreg-online-invitations' ); ?></th>
						<?php if ( $can_edit ) : ?><th><?php esc_html_e( 'Actions', 'prikogstreg-online-invitations' ); ?></th><?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $wishlist_items as $item ) : ?>
						<tr>
							<?php if ( $can_edit ) : ?>
								<td><input type="hidden" name="wishlist_item_ids[]" value="<?php echo esc_attr( (string) ( $item['wishlist_item_id'] ?? '' ) ); ?>" /><?php echo esc_html( (string) ( $item['sort_order'] ?? '' ) ); ?></td>
							<?php endif; ?>
							<td><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></td>
							<td><?php printf( esc_html__( '%1$d / %2$d', 'prikogstreg-online-invitations' ), (int) ( $item['quantity_reserved'] ?? 0 ), (int) ( $item['quantity_requested'] ?? 0 ) ); ?></td>
							<td><?php echo esc_html( (string) ( $item['status'] ?? '' ) ); ?></td>
							<?php if ( $can_edit ) : ?>
								<td><a href="<?php echo esc_url( add_query_arg( 'pks_oi_edit_item', (int) ( $item['wishlist_item_id'] ?? 0 ) ) ); ?>"><?php esc_html_e( 'Edit', 'prikogstreg-online-invitations' ); ?></a></td>
							<?php endif; ?>
						</tr>
						<?php if ( $show_reserver_identity && ! empty( $item['reservers'] ) ) : ?>
							<tr>
								<td colspan="<?php echo $can_edit ? 5 : 3; ?>">
									<ul class="pks-oi-wishlist-reservers">
										<?php foreach ( $item['reservers'] as $reserver ) : ?>
											<li><?php echo esc_html( (string) ( $reserver['display_name'] ?? '' ) ); ?> (<?php echo esc_html( (string) ( $reserver['quantity'] ?? 1 ) ); ?>)</li>
										<?php endforeach; ?>
									</ul>
								</td>
							</tr>
						<?php endif; ?>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( $can_edit && ! empty( $wishlist_items ) ) : ?>
				<p><button type="submit" name="pks_oi_action" value="reorder_wishlist" class="button"><?php esc_html_e( 'Save order', 'prikogstreg-online-invitations' ); ?></button></p>
			<?php endif; ?>
		</form>
	</section>
</div>
