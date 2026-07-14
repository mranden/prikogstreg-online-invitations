<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<p><?php printf( esc_html__( '%d delivery records tracked for this project.', 'prikogstreg-online-invitations' ), (int) ( $counts['deliveries'] ?? 0 ) ); ?></p>
<?php if ( ! empty( $delivery_failures ) ) : ?>
	<h3><?php esc_html_e( 'Delivery failures', 'prikogstreg-online-invitations' ); ?></h3>
	<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Type', 'prikogstreg-online-invitations' ); ?></th><th><?php esc_html_e( 'Status', 'prikogstreg-online-invitations' ); ?></th><th><?php esc_html_e( 'Error', 'prikogstreg-online-invitations' ); ?></th></tr></thead><tbody>
	<?php foreach ( $delivery_failures as $row ) : ?>
		<tr>
			<td><?php echo esc_html( (string) ( $row['delivery_type'] ?? '' ) ); ?></td>
			<td><?php echo esc_html( (string) ( $row['status'] ?? '' ) ); ?></td>
			<td><code><?php echo esc_html( (string) ( $row['last_error_code'] ?? '' ) ); ?></code></td>
		</tr>
	<?php endforeach; ?>
	</tbody></table>
<?php else : ?>
	<p><?php esc_html_e( 'No delivery failures recorded.', 'prikogstreg-online-invitations' ); ?></p>
<?php endif; ?>
