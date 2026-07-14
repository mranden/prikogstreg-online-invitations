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

$summary    = $guest_list['summary'] ?? [];
$items      = $guest_list['items'] ?? [];
$guest_url  = Endpoints::project_url( $project_id, ProjectSections::GUESTS );
$export_url = wp_nonce_url(
	add_query_arg(
		[
			'pks_oi_export_guests' => '1',
			'pks_oi_project_id'    => $project_id,
		],
		$guest_url
	),
	ProjectController::NONCE_ACTION
);
$guest_stats = [
	[ 'label' => __( 'Guests', 'prikogstreg-online-invitations' ), 'value' => (string) (int) ( $summary['total'] ?? 0 ) ],
	[ 'label' => __( 'Attending', 'prikogstreg-online-invitations' ), 'value' => (string) (int) ( $summary['attending'] ?? 0 ) ],
	[ 'label' => __( 'Opened', 'prikogstreg-online-invitations' ), 'value' => (string) (int) ( $summary['opened'] ?? 0 ) ],
	[ 'label' => __( 'Not sent', 'prikogstreg-online-invitations' ), 'value' => (string) (int) ( $summary['not_sent'] ?? 0 ) ],
];
$attendee_count_enabled = ! empty( $project['attendee_count_enabled'] );
?>
<?php pks_oi_project_open(); ?>
	<?php pks_oi_render_notices( $notices ); ?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<?php
	pks_oi_section_open(
		'pks-oi-guests-title',
		__( 'Guests', 'prikogstreg-online-invitations' ),
		__( 'Add guests, import a CSV list, and send personal invitation links.', 'prikogstreg-online-invitations' )
	);
	?>

	<?php pks_oi_render_stats( $guest_stats ); ?>

	<?php if ( '' !== (string) ( $flashed_link ?? '' ) ) : ?>
		<div class="pks-oi-card pks-oi-card--success" role="status">
			<p><?php esc_html_e( 'Personal invitation link (copy now — shown once):', 'prikogstreg-online-invitations' ); ?></p>
			<input type="text" readonly class="pks-oi-field__control" value="<?php echo esc_attr( (string) $flashed_link ); ?>" onclick="this.select();" />
		</div>
	<?php endif; ?>

	<?php if ( $can_edit ) : ?>
		<div class="pks-oi-section__toolbar">
			<a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'prikogstreg-online-invitations' ); ?></a>
		</div>

		<?php pks_oi_panel_open( __( 'Add guest', 'prikogstreg-online-invitations' ), is_array( $edit_guest ) ); ?>
			<form method="post" class="pks-oi-form">
				<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
				<input type="hidden" name="pks_oi_action" value="save_guest" />
				<input type="hidden" name="guest_id" value="<?php echo esc_attr( (string) ( $edit_guest['guest_id'] ?? 0 ) ); ?>" />
				<div class="pks-oi-form-grid">
					<?php
					pks_oi_render_field( [ 'id' => 'pks-oi-guest-name', 'name' => 'display_name', 'label' => __( 'Display name', 'prikogstreg-online-invitations' ), 'value' => (string) ( $edit_guest['display_name'] ?? '' ), 'required' => true ] );
					pks_oi_render_field( [ 'id' => 'pks-oi-guest-email', 'name' => 'email', 'label' => __( 'E-mail (optional)', 'prikogstreg-online-invitations' ), 'type' => 'email', 'value' => (string) ( $edit_guest['email'] ?? '' ) ] );
					pks_oi_render_field( [ 'id' => 'pks-oi-guest-phone', 'name' => 'phone', 'label' => __( 'Phone', 'prikogstreg-online-invitations' ), 'value' => (string) ( $edit_guest['phone'] ?? '' ) ] );
					pks_oi_render_field( [ 'id' => 'pks-oi-guest-party', 'name' => 'party_label', 'label' => __( 'Party label', 'prikogstreg-online-invitations' ), 'value' => (string) ( $edit_guest['party_label'] ?? '' ) ] );
					if ( $attendee_count_enabled ) {
						pks_oi_render_field(
							[
								'id'    => 'pks-oi-guest-attendee-count',
								'name'  => 'attendee_count',
								'label' => __( 'Number of guests', 'prikogstreg-online-invitations' ),
								'type'  => 'number',
								'value' => (string) ( $edit_guest['attendee_count'] ?? '' ),
								'hint'  => __( 'How many people this invitation is for. The guest confirms the number when they respond.', 'prikogstreg-online-invitations' ),
								'min'   => '1',
								'max'   => '50',
							]
						);
					}
					?>
				</div>
				<?php pks_oi_form_actions( __( 'Save guest', 'prikogstreg-online-invitations' ) ); ?>
			</form>
		<?php pks_oi_panel_close(); ?>

		<?php pks_oi_panel_open( __( 'Import CSV', 'prikogstreg-online-invitations' ) ); ?>
			<form method="post" enctype="multipart/form-data" class="pks-oi-form">
				<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
				<input type="hidden" name="pks_oi_action" value="import_preview" />
				<?php
				pks_oi_render_field(
					[
						'id'   => 'pks-oi-guest-csv',
						'name' => 'guest_csv',
						'label' => __( 'Guest CSV file', 'prikogstreg-online-invitations' ),
						'type'   => 'file',
						'hint'   => $attendee_count_enabled
							? __( 'Columns: display name, email, phone, party label, number of guests.', 'prikogstreg-online-invitations' )
							: __( 'Columns: display name, email, phone, party label.', 'prikogstreg-online-invitations' ),
						'wide'   => true,
						'accept' => '.csv,text/csv',
					]
				);
				?>
				<?php pks_oi_form_actions( __( 'Preview import', 'prikogstreg-online-invitations' ) ); ?>
			</form>
			<?php if ( ! empty( $import_preview['rows'] ) ) : ?>
				<form method="post" class="pks-oi-form">
					<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
					<input type="hidden" name="pks_oi_action" value="import_confirm" />
					<p><?php printf( esc_html__( 'Ready to import %d guests.', 'prikogstreg-online-invitations' ), (int) ( $import_preview['total_rows'] ?? 0 ) ); ?></p>
					<?php pks_oi_form_actions( __( 'Confirm import', 'prikogstreg-online-invitations' ) ); ?>
				</form>
			<?php endif; ?>
		<?php pks_oi_panel_close(); ?>
	<?php endif; ?>

	<?php if ( [] === $items ) : ?>
		<?php
		pks_oi_render_empty_state(
			__( 'No guests yet', 'prikogstreg-online-invitations' ),
			__( 'Add guests manually or import a CSV to start sending invitations.', 'prikogstreg-online-invitations' ),
			$can_edit ? null : null
		);
		?>
	<?php else : ?>
		<div class="pks-oi-bulk-bar" data-pks-oi-bulk-bar>
			<span data-pks-oi-bulk-count>0</span> <?php esc_html_e( 'selected', 'prikogstreg-online-invitations' ); ?>
		</div>

		<form method="post" class="pks-oi-guest-list-form">
			<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
			<div class="pks-oi-table-wrap">
				<table class="pks-oi-table pks-oi-guest-table">
					<thead>
						<tr>
							<?php if ( $can_edit ) : ?><th><input type="checkbox" data-pks-oi-select-all aria-label="<?php esc_attr_e( 'Select all guests', 'prikogstreg-online-invitations' ); ?>" /></th><?php endif; ?>
							<th><?php esc_html_e( 'Name', 'prikogstreg-online-invitations' ); ?></th>
							<th><?php esc_html_e( 'E-mail', 'prikogstreg-online-invitations' ); ?></th>
							<th><?php esc_html_e( 'RSVP', 'prikogstreg-online-invitations' ); ?></th>
							<?php if ( $attendee_count_enabled ) : ?>
								<th><?php esc_html_e( 'Guests', 'prikogstreg-online-invitations' ); ?></th>
							<?php endif; ?>
							<th><?php esc_html_e( 'Invitation', 'prikogstreg-online-invitations' ); ?></th>
							<?php if ( $can_edit ) : ?><th><?php esc_html_e( 'Actions', 'prikogstreg-online-invitations' ); ?></th><?php endif; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $items as $guest ) : ?>
							<tr>
								<?php if ( $can_edit ) : ?>
									<td data-label="<?php esc_attr_e( 'Select', 'prikogstreg-online-invitations' ); ?>"><input type="checkbox" name="guest_ids[]" value="<?php echo esc_attr( (string) ( $guest['guest_id'] ?? '' ) ); ?>" data-pks-oi-row-checkbox /></td>
								<?php endif; ?>
								<td data-label="<?php esc_attr_e( 'Name', 'prikogstreg-online-invitations' ); ?>"><?php echo esc_html( (string) ( $guest['display_name'] ?? '' ) ); ?></td>
								<td data-label="<?php esc_attr_e( 'E-mail', 'prikogstreg-online-invitations' ); ?>"><?php echo esc_html( (string) ( $guest['email'] ?? '' ) ); ?></td>
								<td data-label="<?php esc_attr_e( 'RSVP', 'prikogstreg-online-invitations' ); ?>"><?php pks_oi_rsvp_badge( (string) ( $guest['rsvp_status'] ?? '' ) ); ?></td>
								<?php if ( $attendee_count_enabled ) : ?>
									<td data-label="<?php esc_attr_e( 'Guests', 'prikogstreg-online-invitations' ); ?>">
										<?php
										$guest_count = $guest['attendee_count'] ?? null;
										if ( null === $guest_count || '' === (string) $guest_count ) {
											echo '—';
										} else {
											echo esc_html( (string) (int) $guest_count );
										}
										?>
									</td>
								<?php endif; ?>
								<td data-label="<?php esc_attr_e( 'Invitation', 'prikogstreg-online-invitations' ); ?>"><?php pks_oi_invitation_badge( (string) ( $guest['invitation_status'] ?? '' ) ); ?></td>
								<?php if ( $can_edit ) : ?>
									<td data-label="<?php esc_attr_e( 'Actions', 'prikogstreg-online-invitations' ); ?>">
										<div class="pks-oi-table__actions">
											<a href="<?php echo esc_url( add_query_arg( 'pks_oi_edit_guest', (int) ( $guest['guest_id'] ?? 0 ), $guest_url ) ); ?>"><?php esc_html_e( 'Edit', 'prikogstreg-online-invitations' ); ?></a>
											<button type="submit" name="pks_oi_action" value="regenerate_link" class="button-link" formaction="" formmethod="post" onclick="this.form.guest_id.value='<?php echo esc_attr( (string) ( $guest['guest_id'] ?? '' ) ); ?>';"><?php esc_html_e( 'Copy link', 'prikogstreg-online-invitations' ); ?></button>
										</div>
									</td>
								<?php endif; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<input type="hidden" name="guest_id" value="0" />
			<?php if ( $can_edit ) : ?>
				<div class="pks-oi-form__actions">
					<button type="submit" name="pks_oi_action" value="send_invitations" class="button button-primary"><?php esc_html_e( 'Send invitations to selected', 'prikogstreg-online-invitations' ); ?></button>
					<button type="submit" name="pks_oi_action" value="archive_guests" class="button"><?php esc_html_e( 'Archive selected', 'prikogstreg-online-invitations' ); ?></button>
				</div>
			<?php endif; ?>
		</form>
	<?php endif; ?>

	<?php pks_oi_section_close(); ?>
<?php pks_oi_project_close(); ?>
