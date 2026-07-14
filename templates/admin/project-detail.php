<?php
/**
 * Read-only admin detail view for an invitation project.
 *
 * @package PrikOgStreg\OnlineInvitations
 *
 * @var array<string, mixed> $project
 * @var string               $owner_label
 * @var string               $owner_email
 * @var int                  $order_id
 * @var int                  $order_item_id
 * @var string               $order_url
 * @var int                  $product_id
 * @var string               $product_name
 * @var string|null          $effective_expiry
 * @var array<string, mixed> $storage
 * @var array<string, int>   $counts
 * @var list<array<string,mixed>> $delivery_failures
 * @var list<array<string,mixed>> $recent_events
 * @var string               $my_account_url
 * @var string               $back_url
 * @var bool                 $read_only
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$project_id = (int) ( $project['project_id'] ?? 0 );
?>
<div class="pks-oi-admin-support pks-oi-admin-projects__detail">
	<table class="widefat striped">
		<tbody>
			<tr><th><?php esc_html_e( 'Project ID', 'prikogstreg-online-invitations' ); ?></th><td>#<?php echo esc_html( (string) $project_id ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Owner', 'prikogstreg-online-invitations' ); ?></th><td><?php echo esc_html( $owner_label ); ?> &lt;<?php echo esc_html( $owner_email ); ?>&gt;</td></tr>
			<tr><th><?php esc_html_e( 'Order', 'prikogstreg-online-invitations' ); ?></th><td><?php if ( '' !== $order_url ) : ?><a href="<?php echo esc_url( $order_url ); ?>">#<?php echo esc_html( (string) $order_id ); ?></a><?php else : ?>#<?php echo esc_html( (string) $order_id ); ?><?php endif; ?> / <?php esc_html_e( 'item', 'prikogstreg-online-invitations' ); ?> <?php echo esc_html( (string) $order_item_id ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Product', 'prikogstreg-online-invitations' ); ?></th><td><?php echo esc_html( $product_name ); ?> (<?php echo esc_html( (string) $product_id ); ?>)</td></tr>
			<tr><th><?php esc_html_e( 'Status', 'prikogstreg-online-invitations' ); ?></th><td><?php echo esc_html( (string) ( $project['status'] ?? '' ) ); ?> / <?php echo esc_html( (string) ( $project['publication_status'] ?? '' ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Event', 'prikogstreg-online-invitations' ); ?></th><td><?php echo esc_html( (string) ( $project['event_title'] ?? '' ) ); ?> — <?php echo esc_html( (string) ( $project['event_start_utc'] ?? '' ) ); ?><?php if ( '' !== (string) ( $project['event_end_utc'] ?? '' ) ) : ?> → <?php echo esc_html( (string) $project['event_end_utc'] ); ?><?php endif; ?></td></tr>
			<tr><th><?php esc_html_e( 'Effective expiry', 'prikogstreg-online-invitations' ); ?></th><td><?php echo esc_html( (string) ( $effective_expiry ?? '' ) ); ?><?php if ( '' !== (string) ( $project['expiry_override_utc'] ?? '' ) ) : ?> <em>(<?php esc_html_e( 'override', 'prikogstreg-online-invitations' ); ?>)</em><?php endif; ?></td></tr>
			<tr><th><?php esc_html_e( 'Builder', 'prikogstreg-online-invitations' ); ?></th><td><?php esc_html_e( 'Schema', 'prikogstreg-online-invitations' ); ?> <?php echo esc_html( (string) ( $project['builder_schema_version'] ?? '' ) ); ?> — <?php esc_html_e( 'state', 'prikogstreg-online-invitations' ); ?> v<?php echo esc_html( (string) (int) ( $project['state_version'] ?? 0 ) ); ?> — <?php esc_html_e( 'published', 'prikogstreg-online-invitations' ); ?> v<?php echo esc_html( (string) (int) ( $project['published_version'] ?? 0 ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Last error', 'prikogstreg-online-invitations' ); ?></th><td><code><?php echo esc_html( (string) ( $project['last_error_code'] ?? '' ) ); ?></code></td></tr>
			<tr><th><?php esc_html_e( 'Counts', 'prikogstreg-online-invitations' ); ?></th><td><?php printf( esc_html__( 'Guests: %1$d · Wishlist: %2$d · Photos: %3$d · Deliveries: %4$d', 'prikogstreg-online-invitations' ), (int) ( $counts['guests'] ?? 0 ), (int) ( $counts['wishlist'] ?? 0 ), (int) ( $counts['photos'] ?? 0 ), (int) ( $counts['deliveries'] ?? 0 ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Guest photos', 'prikogstreg-online-invitations' ); ?></th><td><?php echo ! empty( $project['guest_photos_enabled'] ) ? esc_html__( 'Enabled', 'prikogstreg-online-invitations' ) : esc_html__( 'Disabled', 'prikogstreg-online-invitations' ); ?> · <?php echo ! empty( $project['photo_gallery_public_enabled'] ) ? esc_html__( 'Public gallery on', 'prikogstreg-online-invitations' ) : esc_html__( 'Public gallery off', 'prikogstreg-online-invitations' ); ?> · <?php echo '' !== (string) ( $project['photo_access_code_hash'] ?? '' ) ? esc_html__( 'Photo code set', 'prikogstreg-online-invitations' ) : esc_html__( 'Photo code missing', 'prikogstreg-online-invitations' ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Created', 'prikogstreg-online-invitations' ); ?></th><td><?php echo esc_html( (string) ( $project['created_at_utc'] ?? '' ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Updated', 'prikogstreg-online-invitations' ); ?></th><td><?php echo esc_html( (string) ( $project['updated_at_utc'] ?? '' ) ); ?></td></tr>
		</tbody>
	</table>

	<h4><?php esc_html_e( 'Storage health', 'prikogstreg-online-invitations' ); ?></h4>
	<p><?php echo ! empty( $storage['healthy'] ) ? esc_html__( 'Healthy', 'prikogstreg-online-invitations' ) : esc_html__( 'Issues detected', 'prikogstreg-online-invitations' ); ?></p>
	<?php if ( ! empty( $storage['issues'] ) && is_array( $storage['issues'] ) ) : ?>
		<ul><?php foreach ( $storage['issues'] as $issue ) : ?><li><code><?php echo esc_html( (string) $issue ); ?></code></li><?php endforeach; ?></ul>
	<?php endif; ?>

	<p>
		<a class="button" href="<?php echo esc_url( $my_account_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open in My Account', 'prikogstreg-online-invitations' ); ?></a>
		<a class="button" href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Back to list', 'prikogstreg-online-invitations' ); ?></a>
	</p>

	<?php if ( ! empty( $delivery_failures ) ) : ?>
		<h4><?php esc_html_e( 'Delivery failures', 'prikogstreg-online-invitations' ); ?></h4>
		<ul><?php foreach ( $delivery_failures as $row ) : ?><li><strong><?php echo esc_html( (string) ( $row['delivery_type'] ?? '' ) ); ?></strong> — <?php echo esc_html( (string) ( $row['status'] ?? '' ) ); ?> — <code><?php echo esc_html( (string) ( $row['last_error_code'] ?? '' ) ); ?></code></li><?php endforeach; ?></ul>
	<?php endif; ?>

	<?php if ( ! empty( $recent_events ) ) : ?>
		<h4><?php esc_html_e( 'Recent events', 'prikogstreg-online-invitations' ); ?></h4>
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
</div>
