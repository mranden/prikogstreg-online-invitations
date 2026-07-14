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
<?php pks_oi_project_open(); ?>
	<?php pks_oi_render_notices( $notices ); ?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<?php
	pks_oi_section_open(
		'pks-oi-wishlist-title',
		__( 'Wishlist', 'prikogstreg-online-invitations' ),
		__( 'Optional — let guests reserve gifts or link to an external wishlist.', 'prikogstreg-online-invitations' )
	);
	?>

	<?php if ( $can_edit ) : ?>
		<?php pks_oi_render_card_open( __( 'Wishlist settings', 'prikogstreg-online-invitations' ) ); ?>
			<form method="post" class="pks-oi-form pks-oi-wishlist-settings">
				<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
				<input type="hidden" name="pks_oi_action" value="save_wishlist_settings" />
				<?php
				pks_oi_render_field(
					[
						'id'          => 'pks-oi-external-wishlist',
						'name'        => 'external_wishlist_url',
						'label'       => __( 'External Ønskeskyen URL (optional)', 'prikogstreg-online-invitations' ),
						'type'        => 'url',
						'value'       => (string) ( $external_wishlist_url ?? '' ),
						'placeholder' => 'https://',
						'wide'        => true,
					]
				);
				pks_oi_render_field(
					[
						'id'      => 'pks-oi-internal-enabled',
						'name'    => 'internal_wishlist_enabled',
						'label'   => __( 'Enable internal wishlist', 'prikogstreg-online-invitations' ),
						'type'    => 'checkbox',
						'checked' => ! empty( $internal_enabled ),
					]
				);
				pks_oi_render_field(
					[
						'id'      => 'pks-oi-show-reserver',
						'name'    => 'show_reserver_identity',
						'label'   => __( 'Show who reserved each gift (off preserves surprise)', 'prikogstreg-online-invitations' ),
						'type'    => 'checkbox',
						'checked' => ! empty( $show_reserver_identity ),
					]
				);
				?>
				<?php pks_oi_form_actions( __( 'Save wishlist settings', 'prikogstreg-online-invitations' ) ); ?>
			</form>
		<?php pks_oi_render_card_close(); ?>

		<?php pks_oi_panel_open( is_array( $edit_item ) ? __( 'Edit gift', 'prikogstreg-online-invitations' ) : __( 'Add gift', 'prikogstreg-online-invitations' ), is_array( $edit_item ) ); ?>
			<form method="post" class="pks-oi-form">
				<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
				<input type="hidden" name="pks_oi_action" value="save_wishlist_item" />
				<input type="hidden" name="wishlist_item_id" value="<?php echo esc_attr( (string) ( $edit_item['wishlist_item_id'] ?? 0 ) ); ?>" />
				<div class="pks-oi-form-grid">
					<?php
					pks_oi_render_field( [ 'id' => 'pks-oi-wishlist-title-field', 'name' => 'title', 'label' => __( 'Title', 'prikogstreg-online-invitations' ), 'value' => (string) ( $edit_item['title'] ?? '' ), 'required' => true, 'wide' => true ] );
					pks_oi_render_field( [ 'id' => 'pks-oi-wishlist-description', 'name' => 'description', 'label' => __( 'Description', 'prikogstreg-online-invitations' ), 'type' => 'textarea', 'value' => (string) ( $edit_item['description'] ?? '' ), 'wide' => true ] );
					pks_oi_render_field( [ 'id' => 'pks-oi-wishlist-url', 'name' => 'external_url', 'label' => __( 'Product URL (optional)', 'prikogstreg-online-invitations' ), 'type' => 'url', 'value' => (string) ( $edit_item['external_url'] ?? '' ) ] );
					pks_oi_render_field( [ 'id' => 'pks-oi-wishlist-image', 'name' => 'image_url', 'label' => __( 'Image URL (optional)', 'prikogstreg-online-invitations' ), 'type' => 'url', 'value' => (string) ( $edit_item['image_url'] ?? '' ) ] );
					pks_oi_render_field( [ 'id' => 'pks-oi-wishlist-quantity', 'name' => 'quantity_requested', 'label' => __( 'Quantity requested', 'prikogstreg-online-invitations' ), 'type' => 'number', 'value' => (string) ( $edit_item['quantity_requested'] ?? 1 ), 'min' => '1', 'max' => '99' ] );
					?>
				</div>
				<p class="pks-oi-field">
					<label class="pks-oi-field__label" for="pks-oi-wishlist-status"><?php esc_html_e( 'Visibility', 'prikogstreg-online-invitations' ); ?></label>
					<select class="pks-oi-field__control" id="pks-oi-wishlist-status" name="status">
						<option value="<?php echo esc_attr( WishlistItemStatus::ACTIVE ); ?>" <?php selected( (string) ( $edit_item['status'] ?? WishlistItemStatus::ACTIVE ), WishlistItemStatus::ACTIVE ); ?>><?php esc_html_e( 'Active', 'prikogstreg-online-invitations' ); ?></option>
						<option value="<?php echo esc_attr( WishlistItemStatus::HIDDEN ); ?>" <?php selected( (string) ( $edit_item['status'] ?? '' ), WishlistItemStatus::HIDDEN ); ?>><?php esc_html_e( 'Hidden', 'prikogstreg-online-invitations' ); ?></option>
					</select>
				</p>
				<?php pks_oi_form_actions( __( 'Save item', 'prikogstreg-online-invitations' ) ); ?>
			</form>
		<?php pks_oi_panel_close(); ?>
	<?php endif; ?>

	<?php if ( empty( $wishlist_items ) ) : ?>
		<?php
		pks_oi_render_empty_state(
			__( 'No gifts yet', 'prikogstreg-online-invitations' ),
			__( 'Add gifts to your wishlist or link an external Ønskeskyen list.', 'prikogstreg-online-invitations' )
		);
		?>
	<?php else : ?>
		<div class="pks-oi-wishlist-grid">
			<?php foreach ( $wishlist_items as $item ) : ?>
				<?php
				$reserved = (int) ( $item['quantity_reserved'] ?? 0 );
				$requested = max( 1, (int) ( $item['quantity_requested'] ?? 1 ) );
				$percent   = min( 100, (int) round( ( $reserved / $requested ) * 100 ) );
				?>
				<article class="pks-oi-wishlist-card">
					<h4 class="pks-oi-wishlist-card__title"><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></h4>
					<p class="pks-oi-field__hint"><?php printf( esc_html__( '%1$d of %2$d reserved', 'prikogstreg-online-invitations' ), $reserved, $requested ); ?></p>
					<div class="pks-oi-wishlist-card__progress" aria-hidden="true"><span style="width: <?php echo esc_attr( (string) $percent ); ?>%;"></span></div>
					<?php pks_oi_render_badge( (string) ( $item['status'] ?? '' ), WishlistItemStatus::ACTIVE === (string) ( $item['status'] ?? '' ) ? 'success' : 'neutral' ); ?>
					<?php if ( $can_edit ) : ?>
						<p><a href="<?php echo esc_url( add_query_arg( 'pks_oi_edit_item', (int) ( $item['wishlist_item_id'] ?? 0 ) ) ); ?>"><?php esc_html_e( 'Edit', 'prikogstreg-online-invitations' ); ?></a></p>
					<?php endif; ?>
					<?php if ( ! empty( $show_reserver_identity ) && ! empty( $item['reservers'] ) ) : ?>
						<ul class="pks-oi-wishlist-reservers">
							<?php foreach ( $item['reservers'] as $reserver ) : ?>
								<li><?php echo esc_html( (string) ( $reserver['display_name'] ?? '' ) ); ?> (<?php echo esc_html( (string) ( $reserver['quantity'] ?? 1 ) ); ?>)</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php pks_oi_section_close(); ?>
<?php pks_oi_project_close(); ?>
