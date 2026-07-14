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

use PrikOgStreg\OnlineInvitations\MyAccount\Endpoints;
use PrikOgStreg\OnlineInvitations\MyAccount\ProjectController;
use PrikOgStreg\OnlineInvitations\MyAccount\ProjectSections;

$contact_items = $contacts['items'] ?? [];
?>
<div class="pks-oi pks-oi-myaccount pks-oi-project">
	<?php pks_oi_render_notices( $notices ); ?>
	<?php if ( $is_support ) : ?>
		<?php pks_oi_render_notices( [ [ 'type' => 'warning', 'message' => __( 'Support view — address book access has been logged.', 'prikogstreg-online-invitations' ) ] ] ); ?>
	<?php endif; ?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<section class="pks-oi-address-book" aria-labelledby="pks-oi-address-book-title">
		<h3 id="pks-oi-address-book-title"><?php esc_html_e( 'Address book', 'prikogstreg-online-invitations' ); ?></h3>
		<p><?php esc_html_e( 'Private contacts reusable across your projects. Adding to a project creates a snapshot — later edits here do not change existing guests.', 'prikogstreg-online-invitations' ); ?></p>

		<form method="get" class="pks-oi-form pks-oi-search-form">
			<input type="hidden" name="pks_oi_ab_search" value="1" />
			<p>
				<label for="pks-oi-ab-search"><?php esc_html_e( 'Search', 'prikogstreg-online-invitations' ); ?></label>
				<input type="search" id="pks-oi-ab-search" name="pks_oi_ab_search" value="<?php echo esc_attr( (string) ( $search ?? '' ) ); ?>" />
				<button type="submit" class="button"><?php esc_html_e( 'Search', 'prikogstreg-online-invitations' ); ?></button>
			</p>
		</form>

		<?php if ( $can_edit ) : ?>
			<h4><?php echo is_array( $edit_contact ) ? esc_html__( 'Edit contact', 'prikogstreg-online-invitations' ) : esc_html__( 'Add contact', 'prikogstreg-online-invitations' ); ?></h4>
			<form method="post" class="pks-oi-form">
				<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
				<input type="hidden" name="pks_oi_action" value="save_contact" />
				<input type="hidden" name="address_book_id" value="<?php echo esc_attr( (string) ( $edit_contact['address_book_id'] ?? 0 ) ); ?>" />
				<p>
					<label for="pks-oi-contact-name"><?php esc_html_e( 'Display name', 'prikogstreg-online-invitations' ); ?></label><br />
					<input type="text" id="pks-oi-contact-name" name="display_name" required value="<?php echo esc_attr( (string) ( $edit_contact['display_name'] ?? '' ) ); ?>" />
				</p>
				<p>
					<label for="pks-oi-contact-email"><?php esc_html_e( 'E-mail', 'prikogstreg-online-invitations' ); ?></label><br />
					<input type="email" id="pks-oi-contact-email" name="email" value="<?php echo esc_attr( (string) ( $edit_contact['email'] ?? '' ) ); ?>" />
				</p>
				<p>
					<label for="pks-oi-contact-phone"><?php esc_html_e( 'Phone', 'prikogstreg-online-invitations' ); ?></label><br />
					<input type="text" id="pks-oi-contact-phone" name="phone" value="<?php echo esc_attr( (string) ( $edit_contact['phone'] ?? '' ) ); ?>" />
				</p>
				<p>
					<label for="pks-oi-contact-notes"><?php esc_html_e( 'Notes', 'prikogstreg-online-invitations' ); ?></label><br />
					<textarea id="pks-oi-contact-notes" name="notes" rows="3"><?php echo esc_textarea( (string) ( $edit_contact['notes'] ?? '' ) ); ?></textarea>
				</p>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save contact', 'prikogstreg-online-invitations' ); ?></button></p>
			</form>
		<?php endif; ?>

		<form method="post" class="pks-oi-address-book-list">
			<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
			<table class="shop_table shop_table_responsive">
				<thead>
					<tr>
						<?php if ( $can_edit ) : ?><th><input type="checkbox" data-pks-oi-select-all /></th><?php endif; ?>
						<th><?php esc_html_e( 'Name', 'prikogstreg-online-invitations' ); ?></th>
						<th><?php esc_html_e( 'E-mail', 'prikogstreg-online-invitations' ); ?></th>
						<th><?php esc_html_e( 'Phone', 'prikogstreg-online-invitations' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $contact_items as $contact ) : ?>
						<tr>
							<?php if ( $can_edit ) : ?>
								<td><input type="checkbox" name="address_book_ids[]" value="<?php echo esc_attr( (string) ( $contact['address_book_id'] ?? '' ) ); ?>" /></td>
							<?php endif; ?>
							<td><?php echo esc_html( (string) ( $contact['display_name'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $contact['email'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $contact['phone'] ?? '' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( $can_edit ) : ?>
				<p>
					<button type="submit" name="pks_oi_action" value="add_contacts_to_project" class="button button-primary"><?php esc_html_e( 'Add selected to this project', 'prikogstreg-online-invitations' ); ?></button>
				</p>
			<?php endif; ?>
		</form>
	</section>
</div>
