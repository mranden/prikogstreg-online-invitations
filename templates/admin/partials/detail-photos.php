<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit; }

use PrikOgStreg\OnlineInvitations\Admin\Invitations\InvitationAdminActions;

$photo_list = is_array( $photo_list ?? null ) ? $photo_list : [];
?>
<p><?php printf( esc_html__( '%1$d pending · %2$d approved · %3$d total', 'prikogstreg-online-invitations' ), (int) ( $photo_summary['pending'] ?? 0 ), (int) ( $photo_summary['approved'] ?? 0 ), (int) ( $photo_summary['total'] ?? 0 ) ); ?></p>

<?php if ( ! $can_moderate ) : ?>
	<p><?php esc_html_e( 'You do not have permission to moderate photos.', 'prikogstreg-online-invitations' ); ?></p>
<?php elseif ( [] === $photo_list ) : ?>
	<p><?php esc_html_e( 'No pending photos.', 'prikogstreg-online-invitations' ); ?></p>
<?php else : ?>
	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'File', 'prikogstreg-online-invitations' ); ?></th><th><?php esc_html_e( 'Uploaded', 'prikogstreg-online-invitations' ); ?></th><th><?php esc_html_e( 'Actions', 'prikogstreg-online-invitations' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( $photo_list as $photo ) : ?>
			<tr>
				<td><?php echo esc_html( (string) ( $photo['original_filename'] ?? '' ) ); ?></td>
				<td><?php echo esc_html( (string) ( $photo['created_at_utc'] ?? '' ) ); ?></td>
				<td>
					<?php foreach ( [ 'approve' => __( 'Approve', 'prikogstreg-online-invitations' ), 'reject' => __( 'Reject', 'prikogstreg-online-invitations' ) ] as $action => $label ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
						<input type="hidden" name="action" value="pks_oi_admin_edit" />
						<input type="hidden" name="project_id" value="<?php echo esc_attr( (string) $project_id ); ?>" />
						<input type="hidden" name="tab" value="photos" />
						<input type="hidden" name="pks_oi_admin_action" value="moderate_photo" />
						<input type="hidden" name="photo_id" value="<?php echo esc_attr( (string) (int) ( $photo['photo_id'] ?? 0 ) ); ?>" />
						<input type="hidden" name="photo_action" value="<?php echo esc_attr( $action ); ?>" />
						<?php wp_nonce_field( InvitationAdminActions::NONCE_ACTION . '_' . $project_id ); ?>
						<button type="submit" class="button button-small"><?php echo esc_html( $label ); ?></button>
					</form>
					<?php endforeach; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
