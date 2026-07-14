<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<h2><?php esc_html_e( 'Invitation preview', 'prikogstreg-online-invitations' ); ?></h2>
<p>
	<a class="button" href="<?php echo esc_url( $draft_preview_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Draft preview', 'prikogstreg-online-invitations' ); ?></a>
	<?php if ( '' !== (string) ( $published_preview_url ?? '' ) ) : ?>
		<a class="button" href="<?php echo esc_url( (string) $published_preview_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Published preview', 'prikogstreg-online-invitations' ); ?></a>
	<?php endif; ?>
</p>

<table class="widefat striped">
	<tbody>
		<tr><th><?php esc_html_e( 'Builder schema', 'prikogstreg-online-invitations' ); ?></th><td><?php echo esc_html( (string) ( $project['builder_schema_version'] ?? '' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'State version', 'prikogstreg-online-invitations' ); ?></th><td>v<?php echo esc_html( (string) (int) ( $project['state_version'] ?? 0 ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Published version', 'prikogstreg-online-invitations' ); ?></th><td>v<?php echo esc_html( (string) (int) ( $project['published_version'] ?? 0 ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Envelope preset', 'prikogstreg-online-invitations' ); ?></th><td><?php echo esc_html( (string) ( $project['envelope_preset'] ?? '' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Storage health', 'prikogstreg-online-invitations' ); ?></th><td><?php echo ! empty( $storage['healthy'] ) ? esc_html__( 'Healthy', 'prikogstreg-online-invitations' ) : esc_html__( 'Issues detected', 'prikogstreg-online-invitations' ); ?></td></tr>
	</tbody>
</table>

<?php if ( '' !== $draft_preview_url ) : ?>
	<iframe class="pks-oi-admin-preview-iframe" src="<?php echo esc_url( $draft_preview_url ); ?>" title="<?php esc_attr_e( 'Draft invitation preview', 'prikogstreg-online-invitations' ); ?>" loading="lazy"></iframe>
<?php endif; ?>
