<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<table class="widefat striped">
	<tbody>
		<tr><th><?php esc_html_e( 'Project ID', 'prikogstreg-online-invitations' ); ?></th><td>#<?php echo esc_html( (string) $project_id ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Customer', 'prikogstreg-online-invitations' ); ?></th><td><?php echo esc_html( $owner_label ); ?> &lt;<?php echo esc_html( $owner_email ); ?>&gt;<?php if ( $owner_user_id > 0 && ( $user_url = get_edit_user_link( $owner_user_id ) ) ) : ?> — <a href="<?php echo esc_url( $user_url ); ?>"><?php esc_html_e( 'Open profile', 'prikogstreg-online-invitations' ); ?></a><?php endif; ?></td></tr>
		<tr><th><?php esc_html_e( 'Order', 'prikogstreg-online-invitations' ); ?></th><td><?php if ( '' !== (string) ( $order_url ?? '' ) ) : ?><a href="<?php echo esc_url( (string) $order_url ); ?>">#<?php echo esc_html( (string) (int) ( $order_id ?? 0 ) ); ?></a><?php else : ?>#<?php echo esc_html( (string) (int) ( $order_id ?? 0 ) ); ?><?php endif; ?><?php if ( '' !== (string) ( $order_status_label ?? '' ) ) : ?> — <?php echo esc_html( (string) $order_status_label ); ?><?php endif; ?></td></tr>
		<tr><th><?php esc_html_e( 'Product', 'prikogstreg-online-invitations' ); ?></th><td><?php echo esc_html( (string) ( $product_name ?? '' ) ); ?> (<?php echo esc_html( (string) (int) ( $product_id ?? 0 ) ); ?>)</td></tr>
		<tr><th><?php esc_html_e( 'Status', 'prikogstreg-online-invitations' ); ?></th><td><?php echo esc_html( (string) ( $project['status'] ?? '' ) ); ?> / <?php echo esc_html( (string) ( $project['publication_status'] ?? '' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Event', 'prikogstreg-online-invitations' ); ?></th><td><?php echo esc_html( (string) ( $project['event_title'] ?? '' ) ); ?> — <?php echo esc_html( (string) ( $project['event_start_utc'] ?? '—' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Guests', 'prikogstreg-online-invitations' ); ?></th><td><?php printf( esc_html__( '%1$d total · %2$d attending · %3$d declined · %4$d pending', 'prikogstreg-online-invitations' ), (int) ( $guest_summary['total'] ?? 0 ), (int) ( $guest_summary['attending'] ?? 0 ), (int) ( $guest_summary['declined'] ?? 0 ), (int) ( $guest_summary['pending_rsvp'] ?? 0 ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Photos', 'prikogstreg-online-invitations' ); ?></th><td><?php printf( esc_html__( '%1$d pending · %2$d approved · %3$d total', 'prikogstreg-online-invitations' ), (int) ( $photo_summary['pending'] ?? 0 ), (int) ( $photo_summary['approved'] ?? 0 ), (int) ( $photo_summary['total'] ?? 0 ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Updated', 'prikogstreg-online-invitations' ); ?></th><td><?php echo esc_html( (string) ( $project['updated_at_utc'] ?? '' ) ); ?></td></tr>
	</tbody>
</table>
