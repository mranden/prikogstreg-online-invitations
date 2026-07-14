<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit; }

use PrikOgStreg\OnlineInvitations\Admin\ProjectSupportActions;
?>
<?php if ( ! $can_tools ) : ?>
	<p><?php esc_html_e( 'You do not have permission to run support tools.', 'prikogstreg-online-invitations' ); ?></p>
	<?php return; ?>
<?php endif; ?>

<p><a class="button" href="<?php echo esc_url( (string) ( $retry_import_url ?? '' ) ); ?>"><?php esc_html_e( 'Retry import', 'prikogstreg-online-invitations' ); ?></a></p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pks-oi-support-form">
	<input type="hidden" name="action" value="pks_oi_support_action" />
	<input type="hidden" name="project_id" value="<?php echo esc_attr( (string) $project_id ); ?>" />
	<?php wp_nonce_field( ProjectSupportActions::NONCE_ACTION . '_' . $project_id, '_wpnonce' ); ?>
	<p>
		<label for="pks-oi-support-reason"><?php esc_html_e( 'Audit reason (optional)', 'prikogstreg-online-invitations' ); ?></label><br />
		<input type="text" id="pks-oi-support-reason" name="pks_oi_reason" class="regular-text" />
	</p>
	<p>
		<label for="pks-oi-expiry-override"><?php esc_html_e( 'Expiry override (UTC Y-m-d H:i:s)', 'prikogstreg-online-invitations' ); ?></label><br />
		<input type="text" id="pks-oi-expiry-override" name="expiry_override_utc" placeholder="2026-12-31 23:59:59" />
	</p>
	<p class="submit">
		<button type="submit" name="pks_oi_support_action" value="restrict" class="button"><?php esc_html_e( 'Restrict', 'prikogstreg-online-invitations' ); ?></button>
		<button type="submit" name="pks_oi_support_action" value="restore" class="button"><?php esc_html_e( 'Restore', 'prikogstreg-online-invitations' ); ?></button>
		<button type="submit" name="pks_oi_support_action" value="publish" class="button"><?php esc_html_e( 'Publish', 'prikogstreg-online-invitations' ); ?></button>
		<button type="submit" name="pks_oi_support_action" value="unpublish" class="button"><?php esc_html_e( 'Unpublish', 'prikogstreg-online-invitations' ); ?></button>
		<button type="submit" name="pks_oi_support_action" value="set_expiry_override" class="button"><?php esc_html_e( 'Set expiry override', 'prikogstreg-online-invitations' ); ?></button>
		<button type="submit" name="pks_oi_support_action" value="clear_expiry_override" class="button"><?php esc_html_e( 'Clear expiry override', 'prikogstreg-online-invitations' ); ?></button>
		<button type="submit" name="pks_oi_support_action" value="rotate_generic_token" class="button"><?php esc_html_e( 'Rotate generic token', 'prikogstreg-online-invitations' ); ?></button>
		<button type="submit" name="pks_oi_support_action" value="resend_welcome" class="button"><?php esc_html_e( 'Resend welcome', 'prikogstreg-online-invitations' ); ?></button>
		<button type="submit" name="pks_oi_support_action" value="hard_delete" class="button button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Permanently delete this project and all data?', 'prikogstreg-online-invitations' ) ); ?>');"><?php esc_html_e( 'Hard delete', 'prikogstreg-online-invitations' ); ?></button>
	</p>
</form>
