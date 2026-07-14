<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit; }

use PrikOgStreg\OnlineInvitations\Admin\Invitations\InvitationAdminActions;
use PrikOgStreg\OnlineInvitations\Admin\ProjectAdminListViewModel;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectEventService;

if ( ! $can_edit ) :
	?>
	<p><?php esc_html_e( 'You do not have permission to edit event details.', 'prikogstreg-online-invitations' ); ?></p>
	<table class="widefat striped"><tbody>
		<tr><th><?php esc_html_e( 'Event title', 'prikogstreg-online-invitations' ); ?></th><td><?php echo esc_html( (string) ( $project['event_title'] ?? '' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Start', 'prikogstreg-online-invitations' ); ?></th><td><?php echo esc_html( (string) ( $project['event_start_utc'] ?? '' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Venue', 'prikogstreg-online-invitations' ); ?></th><td><?php echo esc_html( (string) ( $project['venue_name'] ?? '' ) ); ?></td></tr>
	</tbody></table>
	<?php
	return;
endif;
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="pks_oi_admin_edit" />
	<input type="hidden" name="project_id" value="<?php echo esc_attr( (string) $project_id ); ?>" />
	<input type="hidden" name="tab" value="event" />
	<input type="hidden" name="pks_oi_admin_action" value="save_event" />
	<?php wp_nonce_field( InvitationAdminActions::NONCE_ACTION . '_' . $project_id ); ?>

	<table class="form-table" role="presentation">
		<tr><th><label for="event_title"><?php esc_html_e( 'Event title', 'prikogstreg-online-invitations' ); ?></label></th><td><input class="regular-text" type="text" id="event_title" name="event_title" value="<?php echo esc_attr( (string) ( $project['event_title'] ?? '' ) ); ?>" required /></td></tr>
		<tr><th><label for="event_start_utc"><?php esc_html_e( 'Start (UTC)', 'prikogstreg-online-invitations' ); ?></label></th><td><input class="regular-text" type="text" id="event_start_utc" name="event_start_utc" value="<?php echo esc_attr( (string) ( $project['event_start_utc'] ?? '' ) ); ?>" /></td></tr>
		<tr><th><label for="event_end_utc"><?php esc_html_e( 'End (UTC)', 'prikogstreg-online-invitations' ); ?></label></th><td><input class="regular-text" type="text" id="event_end_utc" name="event_end_utc" value="<?php echo esc_attr( (string) ( $project['event_end_utc'] ?? '' ) ); ?>" /></td></tr>
		<tr><th><label for="venue_name"><?php esc_html_e( 'Venue', 'prikogstreg-online-invitations' ); ?></label></th><td><input class="regular-text" type="text" id="venue_name" name="venue_name" value="<?php echo esc_attr( (string) ( $project['venue_name'] ?? '' ) ); ?>" /></td></tr>
		<tr><th><label for="venue_address_line1"><?php esc_html_e( 'Address', 'prikogstreg-online-invitations' ); ?></label></th><td><input class="regular-text" type="text" id="venue_address_line1" name="venue_address_line1" value="<?php echo esc_attr( (string) ( $project['venue_address_line1'] ?? '' ) ); ?>" /></td></tr>
		<tr><th><label for="practical_info"><?php esc_html_e( 'Practical information', 'prikogstreg-online-invitations' ); ?></label></th><td><textarea class="large-text" id="practical_info" name="practical_info" rows="4"><?php echo esc_textarea( (string) ( $project['practical_info'] ?? '' ) ); ?></textarea></td></tr>
		<tr><th><label for="rsvp_deadline_utc"><?php esc_html_e( 'RSVP deadline (UTC)', 'prikogstreg-online-invitations' ); ?></label></th><td><input class="regular-text" type="text" id="rsvp_deadline_utc" name="rsvp_deadline_utc" value="<?php echo esc_attr( (string) ( $project['rsvp_deadline_utc'] ?? '' ) ); ?>" /></td></tr>
	</table>
	<?php submit_button( __( 'Save event details', 'prikogstreg-online-invitations' ) ); ?>
</form>
