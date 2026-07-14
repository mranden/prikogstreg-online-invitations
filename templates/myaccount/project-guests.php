<?php
/**
 * Project guests section.
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

$summary   = $guest_list['summary'] ?? [];
$items     = $guest_list['items'] ?? [];
$pagination = $guest_list;
$export_url = wp_nonce_url(
	add_query_arg(
		[
			'pks_oi_export_guests' => '1',
			'pks_oi_project_id'      => $project_id,
		],
		Endpoints::project_url( $project_id, ProjectSections::GUESTS )
	),
	ProjectController::NONCE_ACTION
);
?>
<div class="pks-oi pks-oi-myaccount pks-oi-project">
	<?php pks_oi_render_notices( $notices ); ?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<section class="pks-oi-guests" aria-labelledby="pks-oi-guests-title">
		<h3 id="pks-oi-guests-title"><?php esc_html_e( 'Guests', 'prikogstreg-online-invitations' ); ?></h3>
		<p class="pks-oi-guests__summary">
			<?php
			printf(
				esc_html__( '%1$d guests · %2$d attending · %3$d opened', 'prikogstreg-online-invitations' ),
				(int) ( $summary['total'] ?? 0 ),
				(int) ( $summary['attending'] ?? 0 ),
				(int) ( $summary['opened'] ?? 0 )
			);
			?>
		</p>

		<?php if ( '' !== (string) ( $flashed_link ?? '' ) ) : ?>
			<div class="pks-oi-notice woocommerce-message" role="status">
				<p><?php esc_html_e( 'Personal invitation link (copy now — shown once):', 'prikogstreg-online-invitations' ); ?></p>
				<input type="text" readonly class="widefat" value="<?php echo esc_attr( (string) $flashed_link ); ?>" onclick="this.select();" />
			</div>
		<?php endif; ?>

		<?php if ( $can_edit ) : ?>
			<div class="pks-oi-guests__actions">
				<a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'prikogstreg-online-invitations' ); ?></a>
			</div>

			<form method="post" enctype="multipart/form-data" class="pks-oi-form pks-oi-import-form">
				<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
				<input type="hidden" name="pks_oi_action" value="import_preview" />
				<p>
					<label for="pks-oi-guest-csv"><?php esc_html_e( 'Import CSV', 'prikogstreg-online-invitations' ); ?></label><br />
					<input type="file" id="pks-oi-guest-csv" name="guest_csv" accept=".csv,text/csv" />
					<button type="submit" class="button"><?php esc_html_e( 'Preview import', 'prikogstreg-online-invitations' ); ?></button>
				</p>
			</form>

			<?php if ( ! empty( $import_preview['rows'] ) ) : ?>
				<form method="post" class="pks-oi-form">
					<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
					<input type="hidden" name="pks_oi_action" value="import_confirm" />
					<p><?php printf( esc_html__( 'Ready to import %d guests.', 'prikogstreg-online-invitations' ), (int) ( $import_preview['total_rows'] ?? 0 ) ); ?></p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Confirm import', 'prikogstreg-online-invitations' ); ?></button>
				</form>
			<?php endif; ?>

			<h4><?php echo is_array( $edit_guest ) ? esc_html__( 'Edit guest', 'prikogstreg-online-invitations' ) : esc_html__( 'Add guest', 'prikogstreg-online-invitations' ); ?></h4>
			<form method="post" class="pks-oi-form">
				<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
				<input type="hidden" name="pks_oi_action" value="save_guest" />
				<input type="hidden" name="guest_id" value="<?php echo esc_attr( (string) ( $edit_guest['guest_id'] ?? 0 ) ); ?>" />
				<p>
					<label for="pks-oi-guest-name"><?php esc_html_e( 'Display name', 'prikogstreg-online-invitations' ); ?></label><br />
					<input type="text" id="pks-oi-guest-name" name="display_name" required value="<?php echo esc_attr( (string) ( $edit_guest['display_name'] ?? '' ) ); ?>" />
				</p>
				<p>
					<label for="pks-oi-guest-email"><?php esc_html_e( 'E-mail (optional)', 'prikogstreg-online-invitations' ); ?></label><br />
					<input type="email" id="pks-oi-guest-email" name="email" value="<?php echo esc_attr( (string) ( $edit_guest['email'] ?? '' ) ); ?>" />
				</p>
				<p>
					<label for="pks-oi-guest-phone"><?php esc_html_e( 'Phone', 'prikogstreg-online-invitations' ); ?></label><br />
					<input type="text" id="pks-oi-guest-phone" name="phone" value="<?php echo esc_attr( (string) ( $edit_guest['phone'] ?? '' ) ); ?>" />
				</p>
				<p>
					<label for="pks-oi-guest-party"><?php esc_html_e( 'Party label', 'prikogstreg-online-invitations' ); ?></label><br />
					<input type="text" id="pks-oi-guest-party" name="party_label" value="<?php echo esc_attr( (string) ( $edit_guest['party_label'] ?? '' ) ); ?>" />
				</p>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save guest', 'prikogstreg-online-invitations' ); ?></button></p>
			</form>
		<?php endif; ?>

		<form method="post" class="pks-oi-guest-list-form">
			<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
			<table class="shop_table shop_table_responsive pks-oi-guest-table">
				<thead>
					<tr>
						<?php if ( $can_edit ) : ?><th><input type="checkbox" data-pks-oi-select-all /></th><?php endif; ?>
						<th><?php esc_html_e( 'Name', 'prikogstreg-online-invitations' ); ?></th>
						<th><?php esc_html_e( 'E-mail', 'prikogstreg-online-invitations' ); ?></th>
						<th><?php esc_html_e( 'RSVP', 'prikogstreg-online-invitations' ); ?></th>
						<th><?php esc_html_e( 'Invitation', 'prikogstreg-online-invitations' ); ?></th>
						<?php if ( $can_edit ) : ?><th><?php esc_html_e( 'Actions', 'prikogstreg-online-invitations' ); ?></th><?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $items as $guest ) : ?>
						<tr>
							<?php if ( $can_edit ) : ?>
								<td><input type="checkbox" name="guest_ids[]" value="<?php echo esc_attr( (string) ( $guest['guest_id'] ?? '' ) ); ?>" /></td>
							<?php endif; ?>
							<td><?php echo esc_html( (string) ( $guest['display_name'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $guest['email'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $guest['rsvp_status'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $guest['invitation_status'] ?? '' ) ); ?></td>
							<?php if ( $can_edit ) : ?>
								<td>
									<a href="<?php echo esc_url( add_query_arg( 'pks_oi_edit_guest', (int) ( $guest['guest_id'] ?? 0 ), Endpoints::project_url( $project_id, ProjectSections::GUESTS ) ) ); ?>"><?php esc_html_e( 'Edit', 'prikogstreg-online-invitations' ); ?></a>
								</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<input type="hidden" name="guest_id" value="0" />
			<?php if ( $can_edit ) : ?>
				<p>
					<button type="submit" name="pks_oi_action" value="send_invitations" class="button button-primary"><?php esc_html_e( 'Send invitations to selected', 'prikogstreg-online-invitations' ); ?></button>
					<button type="submit" name="pks_oi_action" value="archive_guests" class="button"><?php esc_html_e( 'Archive selected', 'prikogstreg-online-invitations' ); ?></button>
				</p>
			<?php endif; ?>
		</form>

		<?php if ( $can_edit ) : ?>
			<?php foreach ( $items as $guest ) : ?>
				<form method="post" class="pks-oi-inline-actions">
					<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
					<input type="hidden" name="guest_id" value="<?php echo esc_attr( (string) ( $guest['guest_id'] ?? '' ) ); ?>" />
					<button type="submit" name="pks_oi_action" value="regenerate_link" class="button-link"><?php echo esc_html( sprintf( __( 'Copy link for %s', 'prikogstreg-online-invitations' ), (string) ( $guest['display_name'] ?? '' ) ) ); ?></button>
					<button type="submit" name="pks_oi_action" value="save_guest_to_address_book" class="button-link"><?php echo esc_html( sprintf( __( 'Save %s to address book', 'prikogstreg-online-invitations' ), (string) ( $guest['display_name'] ?? '' ) ) ); ?></button>
				</form>
			<?php endforeach; ?>
		<?php endif; ?>
	</section>
</div>
