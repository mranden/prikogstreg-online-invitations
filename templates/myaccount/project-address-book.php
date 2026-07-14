<?php
/**
 * Project address book section.
 *
 * @package PrikOgStreg\OnlineInvitations
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/_helpers.php';

$contact_items = $contacts['items'] ?? [];
$total_contacts = (int) ( $contacts['total'] ?? count( $contact_items ) );
?>
<?php pks_oi_project_open(); ?>
	<?php pks_oi_render_notices( $notices ); ?>
	<?php if ( $is_support ) : ?>
		<?php pks_oi_render_notices( [ [ 'type' => 'warning', 'message' => __( 'Support view — address book access has been logged.', 'prikogstreg-online-invitations' ) ] ] ); ?>
	<?php endif; ?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<?php
	pks_oi_section_open(
		'pks-oi-address-book-title',
		__( 'Address book', 'prikogstreg-online-invitations' ),
		__( 'Optional — save private contacts you can reuse across invitations.', 'prikogstreg-online-invitations' )
	);
	?>

	<?php
	pks_oi_render_stats(
		[
			[ 'label' => __( 'Contacts', 'prikogstreg-online-invitations' ), 'value' => (string) $total_contacts ],
		]
	);
	?>

	<form method="get" class="pks-oi-form pks-oi-search-form">
		<?php
		pks_oi_render_field(
			[
				'id'          => 'pks-oi-ab-search',
				'name'        => 'pks_oi_ab_search',
				'label'       => __( 'Search contacts', 'prikogstreg-online-invitations' ),
				'type'        => 'search',
				'value'       => (string) ( $search ?? '' ),
				'wide'        => true,
				'placeholder' => __( 'Name or email', 'prikogstreg-online-invitations' ),
			]
		);
		?>
		<?php pks_oi_form_actions( __( 'Search', 'prikogstreg-online-invitations' ) ); ?>
	</form>

	<?php if ( $can_edit ) : ?>
		<?php pks_oi_panel_open( is_array( $edit_contact ) ? __( 'Edit contact', 'prikogstreg-online-invitations' ) : __( 'Add contact', 'prikogstreg-online-invitations' ), is_array( $edit_contact ) ); ?>
			<form method="post" class="pks-oi-form">
				<?php wp_nonce_field( \PrikOgStreg\OnlineInvitations\MyAccount\ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
				<input type="hidden" name="pks_oi_action" value="save_contact" />
				<input type="hidden" name="address_book_id" value="<?php echo esc_attr( (string) ( $edit_contact['address_book_id'] ?? 0 ) ); ?>" />
				<div class="pks-oi-form-grid">
					<?php
					pks_oi_render_field( [ 'id' => 'pks-oi-contact-name', 'name' => 'display_name', 'label' => __( 'Display name', 'prikogstreg-online-invitations' ), 'value' => (string) ( $edit_contact['display_name'] ?? '' ), 'required' => true ] );
					pks_oi_render_field( [ 'id' => 'pks-oi-contact-email', 'name' => 'email', 'label' => __( 'E-mail', 'prikogstreg-online-invitations' ), 'type' => 'email', 'value' => (string) ( $edit_contact['email'] ?? '' ) ] );
					pks_oi_render_field( [ 'id' => 'pks-oi-contact-phone', 'name' => 'phone', 'label' => __( 'Phone', 'prikogstreg-online-invitations' ), 'value' => (string) ( $edit_contact['phone'] ?? '' ) ] );
					pks_oi_render_field( [ 'id' => 'pks-oi-contact-notes', 'name' => 'notes', 'label' => __( 'Notes', 'prikogstreg-online-invitations' ), 'type' => 'textarea', 'value' => (string) ( $edit_contact['notes'] ?? '' ), 'wide' => true, 'rows' => 3 ] );
					?>
				</div>
				<?php pks_oi_form_actions( __( 'Save contact', 'prikogstreg-online-invitations' ) ); ?>
			</form>
		<?php pks_oi_panel_close(); ?>
	<?php endif; ?>

	<?php if ( [] === $contact_items ) : ?>
		<?php
		pks_oi_render_empty_state(
			__( 'No contacts yet', 'prikogstreg-online-invitations' ),
			__( 'Save contacts here to quickly add them to future guest lists.', 'prikogstreg-online-invitations' )
		);
		?>
	<?php else : ?>
		<form method="post" class="pks-oi-address-book-list">
			<?php wp_nonce_field( \PrikOgStreg\OnlineInvitations\MyAccount\ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
			<div class="pks-oi-table-wrap">
				<table class="pks-oi-table">
					<thead>
						<tr>
							<?php if ( $can_edit ) : ?><th><input type="checkbox" data-pks-oi-select-all aria-label="<?php esc_attr_e( 'Select all contacts', 'prikogstreg-online-invitations' ); ?>" /></th><?php endif; ?>
							<th><?php esc_html_e( 'Name', 'prikogstreg-online-invitations' ); ?></th>
							<th><?php esc_html_e( 'E-mail', 'prikogstreg-online-invitations' ); ?></th>
							<th><?php esc_html_e( 'Phone', 'prikogstreg-online-invitations' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $contact_items as $contact ) : ?>
							<tr>
								<?php if ( $can_edit ) : ?>
									<td data-label="<?php esc_attr_e( 'Select', 'prikogstreg-online-invitations' ); ?>"><input type="checkbox" name="address_book_ids[]" value="<?php echo esc_attr( (string) ( $contact['address_book_id'] ?? '' ) ); ?>" data-pks-oi-row-checkbox /></td>
								<?php endif; ?>
								<td data-label="<?php esc_attr_e( 'Name', 'prikogstreg-online-invitations' ); ?>"><?php echo esc_html( (string) ( $contact['display_name'] ?? '' ) ); ?></td>
								<td data-label="<?php esc_attr_e( 'E-mail', 'prikogstreg-online-invitations' ); ?>"><?php echo esc_html( (string) ( $contact['email'] ?? '' ) ); ?></td>
								<td data-label="<?php esc_attr_e( 'Phone', 'prikogstreg-online-invitations' ); ?>"><?php echo esc_html( (string) ( $contact['phone'] ?? '' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php if ( $can_edit ) : ?>
				<input type="hidden" name="pks_oi_action" value="add_contacts_to_project" />
				<?php pks_oi_form_actions( __( 'Add selected to this project', 'prikogstreg-online-invitations' ) ); ?>
			<?php endif; ?>
		</form>
	<?php endif; ?>

	<?php pks_oi_section_close(); ?>
<?php pks_oi_project_close(); ?>
