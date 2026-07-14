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
<div class="pks-oi pks-oi-myaccount pks-oi-project">
	<?php pks_oi_render_notices( $notices ); ?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<section aria-labelledby="pks-oi-settings-title">
		<h3 id="pks-oi-settings-title"><?php esc_html_e( 'Privacy and project lifecycle', 'prikogstreg-online-invitations' ); ?></h3>

		<?php if ( isset( $_GET['pks_oi_archived_project'] ) ) : ?>
			<p class="woocommerce-message"><?php esc_html_e( 'This project has been archived. Public access and scheduled sends are stopped.', 'prikogstreg-online-invitations' ); ?></p>
		<?php endif; ?>

		<?php if ( isset( $_GET['pks_oi_restored_project'] ) ) : ?>
			<p class="woocommerce-message"><?php esc_html_e( 'This project has been restored from archive.', 'prikogstreg-online-invitations' ); ?></p>
		<?php endif; ?>

		<?php if ( isset( $_GET['pks_oi_delete_error'] ) ) : ?>
			<p class="woocommerce-error"><?php echo esc_html( sanitize_text_field( wp_unslash( (string) $_GET['pks_oi_delete_error'] ) ) ); ?></p>
		<?php endif; ?>

		<p class="pks-oi-form__description description"><?php esc_html_e( 'Archiving stops public access and pending deliveries. Your data is kept until you delete the project. Permanent deletion removes invitation files, guests, photos, and related records. Your WooCommerce order may still be retained for legal purposes.', 'prikogstreg-online-invitations' ); ?></p>

		<?php if ( $can_edit && ! $is_archived ) : ?>
			<form method="post" action="" class="pks-oi-form">
				<?php wp_nonce_field( \PrikOgStreg\OnlineInvitations\MyAccount\ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
				<input type="hidden" name="pks_oi_project_id" value="<?php echo esc_attr( (string) $project_id ); ?>" />
				<p><button type="submit" name="pks_oi_action" value="archive_project" class="button"><?php esc_html_e( 'Archive project', 'prikogstreg-online-invitations' ); ?></button></p>
			</form>
		<?php elseif ( $is_archived ) : ?>
			<form method="post" action="" class="pks-oi-form">
				<?php wp_nonce_field( \PrikOgStreg\OnlineInvitations\MyAccount\ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
				<input type="hidden" name="pks_oi_project_id" value="<?php echo esc_attr( (string) $project_id ); ?>" />
				<p><button type="submit" name="pks_oi_action" value="restore_project" class="button"><?php esc_html_e( 'Restore from archive', 'prikogstreg-online-invitations' ); ?></button></p>
			</form>
		<?php endif; ?>

		<form method="post" action="" class="pks-oi-form pks-oi-danger-zone">
			<?php wp_nonce_field( \PrikOgStreg\OnlineInvitations\MyAccount\ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
			<input type="hidden" name="pks_oi_project_id" value="<?php echo esc_attr( (string) $project_id ); ?>" />
			<h4><?php esc_html_e( 'Delete project permanently', 'prikogstreg-online-invitations' ); ?></h4>
			<p>
				<label for="pks_oi_delete_confirmation">
					<?php
					printf(
						/* translators: %s: confirmation word */
						esc_html__( 'Type %s to confirm permanent deletion:', 'prikogstreg-online-invitations' ),
						esc_html( (string) ( $delete_confirmation_phrase ?? 'DELETE' ) )
					);
					?>
				</label>
			</p>
			<p><input type="text" id="pks_oi_delete_confirmation" name="pks_oi_delete_confirmation" required autocomplete="off" /></p>
			<p><button type="submit" name="pks_oi_action" value="delete_project" class="button button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'This cannot be undone. Delete this project and all related data?', 'prikogstreg-online-invitations' ) ); ?>');"><?php esc_html_e( 'Delete permanently', 'prikogstreg-online-invitations' ); ?></button></p>
		</form>
	</section>
</div>
