<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit; }

use PrikOgStreg\OnlineInvitations\Admin\Invitations\InvitationAdminActions;
use PrikOgStreg\OnlineInvitations\Domain\Guest\RsvpStatus;

$guest_list = is_array( $guest_list ?? null ) ? $guest_list : [ 'items' => [], 'summary' => [] ];
?>
<p><?php printf( esc_html__( '%1$d guests · %2$d attending · %3$d declined · %4$d pending RSVP', 'prikogstreg-online-invitations' ), (int) ( $guest_summary['total'] ?? 0 ), (int) ( $guest_summary['attending'] ?? 0 ), (int) ( $guest_summary['declined'] ?? 0 ), (int) ( $guest_summary['pending_rsvp'] ?? 0 ) ); ?></p>

<?php if ( [] === ( $guest_list['items'] ?? [] ) ) : ?>
	<p><?php esc_html_e( 'No guests on this project.', 'prikogstreg-online-invitations' ); ?></p>
<?php else : ?>
	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'Guest', 'prikogstreg-online-invitations' ); ?></th><th><?php esc_html_e( 'RSVP', 'prikogstreg-online-invitations' ); ?></th><th><?php esc_html_e( 'Attendees', 'prikogstreg-online-invitations' ); ?></th><?php if ( $can_edit ) : ?><th><?php esc_html_e( 'Support edit', 'prikogstreg-online-invitations' ); ?></th><?php endif; ?></tr></thead>
		<tbody>
		<?php foreach ( $guest_list['items'] as $guest ) : ?>
			<tr>
				<td><?php echo esc_html( (string) ( $guest['display_name'] ?? '' ) ); ?><br /><span class="description"><?php echo esc_html( (string) ( $guest['email'] ?? '' ) ); ?></span></td>
				<td><?php echo esc_html( (string) ( $guest['rsvp_status'] ?? '' ) ); ?></td>
				<td><?php echo esc_html( (string) ( $guest['attendee_count'] ?? '—' ) ); ?></td>
				<?php if ( $can_edit ) : ?>
				<td>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pks-oi-inline-support-form">
						<input type="hidden" name="action" value="pks_oi_admin_edit" />
						<input type="hidden" name="project_id" value="<?php echo esc_attr( (string) $project_id ); ?>" />
						<input type="hidden" name="tab" value="guests" />
						<input type="hidden" name="pks_oi_admin_action" value="update_guest" />
						<input type="hidden" name="guest_id" value="<?php echo esc_attr( (string) (int) ( $guest['guest_id'] ?? 0 ) ); ?>" />
						<?php wp_nonce_field( InvitationAdminActions::NONCE_ACTION . '_' . $project_id ); ?>
						<input type="text" name="display_name" value="<?php echo esc_attr( (string) ( $guest['display_name'] ?? '' ) ); ?>" />
						<input type="email" name="email" value="<?php echo esc_attr( (string) ( $guest['email'] ?? '' ) ); ?>" />
						<select name="rsvp_status">
							<?php foreach ( [ RsvpStatus::PENDING, RsvpStatus::ATTENDING, RsvpStatus::DECLINED ] as $status ) : ?>
								<option value="<?php echo esc_attr( $status ); ?>" <?php selected( (string) ( $guest['rsvp_status'] ?? '' ), $status ); ?>><?php echo esc_html( $status ); ?></option>
							<?php endforeach; ?>
						</select>
						<input type="number" name="attendee_count" min="1" max="50" value="<?php echo esc_attr( (string) ( $guest['attendee_count'] ?? '' ) ); ?>" />
						<button type="submit" class="button button-small"><?php esc_html_e( 'Save', 'prikogstreg-online-invitations' ); ?></button>
					</form>
				</td>
				<?php endif; ?>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
