<?php
/**
 * Plugin settings screen.
 *
 * @package PrikOgStreg\OnlineInvitations
 *
 * @var string $plugin_version
 * @var string $db_version
 * @var bool   $diagnostics
 * @var bool   $hpos_enabled
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form method="post">
	<?php wp_nonce_field( 'pks_oi_settings' ); ?>
	<table class="form-table" role="presentation">
		<tr><th scope="row"><?php esc_html_e( 'Plugin version', 'prikogstreg-online-invitations' ); ?></th><td><code><?php echo esc_html( $plugin_version ); ?></code></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Database version', 'prikogstreg-online-invitations' ); ?></th><td><code><?php echo esc_html( $db_version ); ?></code></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'HPOS enabled', 'prikogstreg-online-invitations' ); ?></th><td><?php echo $hpos_enabled ? esc_html__( 'Yes', 'prikogstreg-online-invitations' ) : esc_html__( 'No', 'prikogstreg-online-invitations' ); ?></td></tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Storefront diagnostics', 'prikogstreg-online-invitations' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="pks_oi_admin_diagnostics_enabled" value="1" <?php checked( $diagnostics ); ?> />
					<?php esc_html_e( 'Show product-page readiness diagnostics to shop managers (no secrets).', 'prikogstreg-online-invitations' ); ?>
				</label>
			</td>
		</tr>
	</table>
	<?php submit_button( __( 'Save settings', 'prikogstreg-online-invitations' ), 'primary', 'pks_oi_settings_submit' ); ?>
</form>
