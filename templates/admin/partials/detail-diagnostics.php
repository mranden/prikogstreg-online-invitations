<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<table class="widefat striped">
	<tbody>
		<tr><th><?php esc_html_e( 'Order item ID', 'prikogstreg-online-invitations' ); ?></th><td><?php echo esc_html( (string) (int) ( $order_item_id ?? 0 ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Import error', 'prikogstreg-online-invitations' ); ?></th><td><code><?php echo esc_html( (string) ( $project['last_error_code'] ?? '' ) ); ?></code></td></tr>
		<tr><th><?php esc_html_e( 'State version', 'prikogstreg-online-invitations' ); ?></th><td>v<?php echo esc_html( (string) (int) ( $project['state_version'] ?? 0 ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Published version', 'prikogstreg-online-invitations' ); ?></th><td>v<?php echo esc_html( (string) (int) ( $project['published_version'] ?? 0 ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Generic token', 'prikogstreg-online-invitations' ); ?></th><td><?php echo ! empty( $has_generic_token ) ? esc_html__( 'Present (hash redacted)', 'prikogstreg-online-invitations' ) : esc_html__( 'Not set', 'prikogstreg-online-invitations' ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Storage health', 'prikogstreg-online-invitations' ); ?></th><td><?php echo ! empty( $storage['healthy'] ) ? esc_html__( 'Healthy', 'prikogstreg-online-invitations' ) : esc_html__( 'Issues detected', 'prikogstreg-online-invitations' ); ?></td></tr>
	</tbody>
</table>
<?php if ( ! empty( $storage['issues'] ) && is_array( $storage['issues'] ) ) : ?>
	<ul><?php foreach ( $storage['issues'] as $issue ) : ?><li><code><?php echo esc_html( (string) $issue ); ?></code></li><?php endforeach; ?></ul>
<?php endif; ?>

<?php if ( ! empty( $recent_events ) ) : ?>
	<h3><?php esc_html_e( 'Recent audit events', 'prikogstreg-online-invitations' ); ?></h3>
	<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Type', 'prikogstreg-online-invitations' ); ?></th><th><?php esc_html_e( 'When', 'prikogstreg-online-invitations' ); ?></th><th><?php esc_html_e( 'Actor', 'prikogstreg-online-invitations' ); ?></th></tr></thead><tbody>
	<?php foreach ( $recent_events as $event ) : ?>
		<tr>
			<td><code><?php echo esc_html( (string) ( $event['event_type'] ?? '' ) ); ?></code></td>
			<td><?php echo esc_html( (string) ( $event['created_at_utc'] ?? '' ) ); ?></td>
			<td><?php echo esc_html( (string) ( $event['actor_type'] ?? '' ) ); ?></td>
		</tr>
	<?php endforeach; ?>
	</tbody></table>
<?php endif; ?>
