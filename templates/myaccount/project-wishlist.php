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

$wishlist_base_url = remove_query_arg( 'pks_oi_edit_item' );
$is_editing        = is_array( $edit_item );
$quantity_value    = $is_editing ? (string) (int) ( $edit_item['quantity_requested'] ?? 1 ) : '';
if ( $is_editing && 1 === (int) $quantity_value ) {
	$quantity_value = '';
}
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
	<?php endif; ?>

	<?php if ( empty( $wishlist_items ) ) : ?>
		<?php
		pks_oi_render_empty_state(
			__( 'No gifts yet', 'prikogstreg-online-invitations' ),
			__( 'Add gifts to your wishlist or link an external Ønskeskyen list.', 'prikogstreg-online-invitations' )
		);
		?>
	<?php else : ?>
		<?php pks_oi_render_card_open( __( 'Your gifts', 'prikogstreg-online-invitations' ) ); ?>
			<div class="pks-oi-table-wrap">
				<table class="pks-oi-table pks-oi-wishlist-list">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Title', 'prikogstreg-online-invitations' ); ?></th>
							<th><?php esc_html_e( 'Description', 'prikogstreg-online-invitations' ); ?></th>
							<th><?php esc_html_e( 'Link', 'prikogstreg-online-invitations' ); ?></th>
							<th><?php esc_html_e( 'Qty', 'prikogstreg-online-invitations' ); ?></th>
							<th><?php esc_html_e( 'Reserved', 'prikogstreg-online-invitations' ); ?></th>
							<th><?php esc_html_e( 'Visibility', 'prikogstreg-online-invitations' ); ?></th>
							<?php if ( $can_edit ) : ?>
								<th><?php esc_html_e( 'Actions', 'prikogstreg-online-invitations' ); ?></th>
							<?php endif; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $wishlist_items as $item ) : ?>
							<?php
							$reserved  = (int) ( $item['quantity_reserved'] ?? 0 );
							$requested = max( 1, (int) ( $item['quantity_requested'] ?? 1 ) );
							$link      = trim( (string) ( $item['external_url'] ?? '' ) );
							$is_hidden = WishlistItemStatus::HIDDEN === (string) ( $item['status'] ?? '' );
							?>
							<tr class="<?php echo $is_hidden ? 'is-hidden' : ''; ?>">
								<td data-label="<?php esc_attr_e( 'Title', 'prikogstreg-online-invitations' ); ?>">
									<strong><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></strong>
								</td>
								<td data-label="<?php esc_attr_e( 'Description', 'prikogstreg-online-invitations' ); ?>">
									<?php echo esc_html( (string) ( $item['description'] ?? '' ) ); ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Link', 'prikogstreg-online-invitations' ); ?>">
									<?php if ( '' !== $link ) : ?>
										<a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $link ); ?></a>
									<?php else : ?>
										<span class="pks-oi-table__muted">—</span>
									<?php endif; ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Qty', 'prikogstreg-online-invitations' ); ?>">
									<?php echo esc_html( (string) $requested ); ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Reserved', 'prikogstreg-online-invitations' ); ?>">
									<?php printf( esc_html__( '%1$d of %2$d', 'prikogstreg-online-invitations' ), $reserved, $requested ); ?>
									<?php if ( ! empty( $show_reserver_identity ) && ! empty( $item['reservers'] ) ) : ?>
										<ul class="pks-oi-wishlist-reservers">
											<?php foreach ( $item['reservers'] as $reserver ) : ?>
												<li><?php echo esc_html( (string) ( $reserver['display_name'] ?? '' ) ); ?> (<?php echo esc_html( (string) ( $reserver['quantity'] ?? 1 ) ); ?>)</li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Visibility', 'prikogstreg-online-invitations' ); ?>">
									<?php
									pks_oi_render_badge(
										$is_hidden
											? __( 'Hidden', 'prikogstreg-online-invitations' )
											: __( 'Visible', 'prikogstreg-online-invitations' ),
										$is_hidden ? 'neutral' : 'success'
									);
									?>
								</td>
								<?php if ( $can_edit ) : ?>
									<td data-label="<?php esc_attr_e( 'Actions', 'prikogstreg-online-invitations' ); ?>" class="pks-oi-table__actions">
										<a href="<?php echo esc_url( add_query_arg( 'pks_oi_edit_item', (int) ( $item['wishlist_item_id'] ?? 0 ), $wishlist_base_url ) ); ?>">
											<?php esc_html_e( 'Edit', 'prikogstreg-online-invitations' ); ?>
										</a>
									</td>
								<?php endif; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php pks_oi_render_card_close(); ?>
	<?php endif; ?>

	<?php if ( $can_edit ) : ?>
		<?php pks_oi_render_card_open( $is_editing ? __( 'Edit gift', 'prikogstreg-online-invitations' ) : __( 'Add gift', 'prikogstreg-online-invitations' ) ); ?>
			<form method="post" class="pks-oi-form">
				<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
				<input type="hidden" name="pks_oi_action" value="save_wishlist_item" />
				<input type="hidden" name="wishlist_item_id" value="<?php echo esc_attr( (string) ( $edit_item['wishlist_item_id'] ?? 0 ) ); ?>" />
				<div class="pks-oi-form-grid">
					<?php
					pks_oi_render_field(
						[
							'id'       => 'pks-oi-wishlist-title-field',
							'name'     => 'title',
							'label'    => __( 'Title', 'prikogstreg-online-invitations' ),
							'value'    => (string) ( $edit_item['title'] ?? '' ),
							'required' => true,
							'wide'     => true,
						]
					);
					pks_oi_render_field(
						[
							'id'    => 'pks-oi-wishlist-description',
							'name'  => 'description',
							'label' => __( 'Description', 'prikogstreg-online-invitations' ),
							'type'  => 'textarea',
							'value' => (string) ( $edit_item['description'] ?? '' ),
							'wide'  => true,
						]
					);
					pks_oi_render_field(
						[
							'id'          => 'pks-oi-wishlist-url',
							'name'        => 'external_url',
							'label'       => __( 'Link', 'prikogstreg-online-invitations' ),
							'type'        => 'url',
							'value'       => (string) ( $edit_item['external_url'] ?? '' ),
							'placeholder' => 'https://',
							'wide'        => true,
						]
					);
					pks_oi_render_field(
						[
							'id'          => 'pks-oi-wishlist-quantity',
							'name'        => 'quantity_requested',
							'label'       => __( 'Quantity (optional)', 'prikogstreg-online-invitations' ),
							'type'        => 'number',
							'value'       => $quantity_value,
							'min'         => '1',
							'max'         => '99',
							'placeholder' => '1',
							'hint'        => __( 'Leave empty for a single gift.', 'prikogstreg-online-invitations' ),
						]
					);
					?>
				</div>
				<p class="pks-oi-field">
					<label class="pks-oi-field__label" for="pks-oi-wishlist-status"><?php esc_html_e( 'Visibility', 'prikogstreg-online-invitations' ); ?></label>
					<select class="pks-oi-field__control" id="pks-oi-wishlist-status" name="status">
						<option value="<?php echo esc_attr( WishlistItemStatus::ACTIVE ); ?>" <?php selected( (string) ( $edit_item['status'] ?? WishlistItemStatus::ACTIVE ), WishlistItemStatus::ACTIVE ); ?>><?php esc_html_e( 'Visible', 'prikogstreg-online-invitations' ); ?></option>
						<option value="<?php echo esc_attr( WishlistItemStatus::HIDDEN ); ?>" <?php selected( (string) ( $edit_item['status'] ?? '' ), WishlistItemStatus::HIDDEN ); ?>><?php esc_html_e( 'Hidden', 'prikogstreg-online-invitations' ); ?></option>
					</select>
				</p>
				<?php pks_oi_form_actions( $is_editing ? __( 'Save changes', 'prikogstreg-online-invitations' ) : __( 'Add gift', 'prikogstreg-online-invitations' ) ); ?>
				<?php if ( $is_editing ) : ?>
					<p class="pks-oi-form__secondary-action">
						<a class="button" href="<?php echo esc_url( $wishlist_base_url ); ?>"><?php esc_html_e( 'Cancel edit', 'prikogstreg-online-invitations' ); ?></a>
					</p>
				<?php endif; ?>
			</form>
		<?php pks_oi_render_card_close(); ?>
	<?php endif; ?>

	<?php pks_oi_section_close(); ?>
<?php pks_oi_project_close(); ?>
