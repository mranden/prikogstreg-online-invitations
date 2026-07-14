<?php
/**
 * Project privacy and lifecycle settings.
 *
 * @package PrikOgStreg\OnlineInvitations
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/_helpers.php';

$is_archived = ! empty( $is_archived );
?>
<?php pks_oi_project_open(); ?>
	<?php pks_oi_render_notices( $notices ); ?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<?php
	pks_oi_section_open(
		'pks-oi-settings-title',
		__( 'Privacy and project lifecycle', 'prikogstreg-online-invitations' ),
		__( 'Archive, restore, or permanently delete this invitation project.', 'prikogstreg-online-invitations' )
	);
	?>

	<?php if ( isset( $_GET['pks_oi_archived_project'] ) ) : ?>
		<?php pks_oi_render_notices( [ [ 'type' => 'success', 'message' => __( 'This project has been archived. Public access and scheduled sends are stopped.', 'prikogstreg-online-invitations' ) ] ] ); ?>
	<?php endif; ?>
	<?php if ( isset( $_GET['pks_oi_restored_project'] ) ) : ?>
		<?php pks_oi_render_notices( [ [ 'type' => 'success', 'message' => __( 'This project has been restored from archive.', 'prikogstreg-online-invitations' ) ] ] ); ?>
	<?php endif; ?>
	<?php if ( isset( $_GET['pks_oi_delete_error'] ) ) : ?>
		<?php
		$delete_error_code = sanitize_key( wp_unslash( (string) $_GET['pks_oi_delete_error'] ) );
		pks_oi_render_notices(
			[
				[
					'type'    => 'error',
					'message' => \PrikOgStreg\OnlineInvitations\Domain\Project\ProjectCustomerDeleteService::customer_error_message( $delete_error_code ),
				],
			]
		);
		?>
	<?php endif; ?>

	<dl class="pks-oi-meta-grid">
		<div class="pks-oi-meta-grid__item">
			<dt><?php esc_html_e( 'Project ID', 'prikogstreg-online-invitations' ); ?></dt>
			<dd><?php echo esc_html( (string) $project_id ); ?></dd>
		</div>
		<div class="pks-oi-meta-grid__item">
			<dt><?php esc_html_e( 'Status', 'prikogstreg-online-invitations' ); ?></dt>
			<dd><?php pks_oi_project_status_badge( (string) ( $project['status'] ?? '' ) ); ?></dd>
		</div>
	</dl>

	<?php if ( $can_edit && ! $is_archived ) : ?>
		<?php pks_oi_render_card_open( __( 'Archive project', 'prikogstreg-online-invitations' ), 'pks-oi-card--warning' ); ?>
			<p><?php esc_html_e( 'Archiving stops public access and pending deliveries. Your data is kept until you delete the project.', 'prikogstreg-online-invitations' ); ?></p>
			<form method="post" action="" class="pks-oi-form">
				<?php wp_nonce_field( \PrikOgStreg\OnlineInvitations\MyAccount\ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
				<input type="hidden" name="pks_oi_project_id" value="<?php echo esc_attr( (string) $project_id ); ?>" />
				<input type="hidden" name="pks_oi_action" value="archive_project" />
				<button type="submit" class="button"><?php esc_html_e( 'Archive project', 'prikogstreg-online-invitations' ); ?></button>
			</form>
		<?php pks_oi_render_card_close(); ?>
	<?php elseif ( $is_archived ) : ?>
		<?php pks_oi_render_card_open( __( 'Restore project', 'prikogstreg-online-invitations' ) ); ?>
			<form method="post" action="" class="pks-oi-form">
				<?php wp_nonce_field( \PrikOgStreg\OnlineInvitations\MyAccount\ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
				<input type="hidden" name="pks_oi_project_id" value="<?php echo esc_attr( (string) $project_id ); ?>" />
				<input type="hidden" name="pks_oi_action" value="restore_project" />
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Restore from archive', 'prikogstreg-online-invitations' ); ?></button>
			</form>
		<?php pks_oi_render_card_close(); ?>
	<?php endif; ?>

	<div class="pks-oi-danger-zone">
		<?php pks_oi_render_card_open( __( 'Delete project permanently', 'prikogstreg-online-invitations' ) ); ?>
			<p><?php esc_html_e( 'Permanent deletion removes invitation files, guests, photos, and related records. Your WooCommerce order may still be retained for legal purposes.', 'prikogstreg-online-invitations' ); ?></p>
			<form method="post" action="" class="pks-oi-form">
				<?php wp_nonce_field( \PrikOgStreg\OnlineInvitations\MyAccount\ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
				<input type="hidden" name="pks_oi_project_id" value="<?php echo esc_attr( (string) $project_id ); ?>" />
				<?php
				pks_oi_render_field(
					[
						'id'       => 'pks_oi_delete_confirmation',
						'name'     => 'pks_oi_delete_confirmation',
						'label'    => sprintf(
							/* translators: %s: confirmation word */
							__( 'Type %s to confirm permanent deletion', 'prikogstreg-online-invitations' ),
							(string) ( $delete_confirmation_phrase ?? 'DELETE' )
						),
						'value'    => '',
						'required' => true,
						'wide'     => true,
					]
				);
				?>
				<input type="hidden" name="pks_oi_action" value="delete_project" />
				<button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'This cannot be undone. Delete this project and all related data?', 'prikogstreg-online-invitations' ) ); ?>');"><?php esc_html_e( 'Delete permanently', 'prikogstreg-online-invitations' ); ?></button>
			</form>
		<?php pks_oi_render_card_close(); ?>
	</div>

	<?php pks_oi_section_close(); ?>
<?php pks_oi_project_close(); ?>
