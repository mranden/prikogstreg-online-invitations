<?php
/**
 * Project event details section.
 *
 * @package PrikOgStreg\OnlineInvitations
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/_helpers.php';

$saved = isset( $_GET['pks_oi_saved'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="pks-oi pks-oi-myaccount pks-oi-project">
	<?php pks_oi_render_notices( $notices ); ?>
	<?php if ( $saved ) : ?>
		<?php pks_oi_render_notices( [ [ 'type' => 'success', 'message' => __( 'Event details saved.', 'prikogstreg-online-invitations' ) ] ] ); ?>
	<?php endif; ?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<section class="pks-oi-event" aria-labelledby="pks-oi-event-title">
		<h3 id="pks-oi-event-title"><?php esc_html_e( 'Event details', 'prikogstreg-online-invitations' ); ?></h3>

		<?php if ( ! $can_edit ) : ?>
			<p><?php esc_html_e( 'Event details cannot be edited for this project.', 'prikogstreg-online-invitations' ); ?></p>
		<?php else : ?>
			<form method="post" action="" class="pks-oi-form pks-oi-event-form">
				<?php wp_nonce_field( \PrikOgStreg\OnlineInvitations\MyAccount\ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
				<input type="hidden" name="pks_oi_action" value="save_event" />

				<p>
					<label for="pks-oi-event-title-field"><?php esc_html_e( 'Event title', 'prikogstreg-online-invitations' ); ?></label><br />
					<input type="text" id="pks-oi-event-title-field" name="event_title" value="<?php echo esc_attr( (string) ( $project['event_title'] ?? '' ) ); ?>" required />
				</p>

				<p>
					<label for="pks-oi-timezone"><?php esc_html_e( 'Timezone', 'prikogstreg-online-invitations' ); ?></label><br />
					<input type="text" id="pks-oi-timezone" name="timezone" value="<?php echo esc_attr( (string) ( $project['timezone'] ?? 'Europe/Copenhagen' ) ); ?>" />
				</p>

				<p>
					<label for="pks-oi-event-start"><?php esc_html_e( 'Event start (local)', 'prikogstreg-online-invitations' ); ?></label><br />
					<input type="datetime-local" id="pks-oi-event-start" name="event_start_utc" value="<?php echo esc_attr( (string) ( $project['event_start_local'] ?? '' ) ); ?>" />
				</p>

				<p>
					<label for="pks-oi-event-end"><?php esc_html_e( 'Event end (local)', 'prikogstreg-online-invitations' ); ?></label><br />
					<input type="datetime-local" id="pks-oi-event-end" name="event_end_utc" value="<?php echo esc_attr( (string) ( $project['event_end_local'] ?? '' ) ); ?>" />
				</p>

				<p>
					<label for="pks-oi-rsvp-deadline"><?php esc_html_e( 'RSVP deadline (local)', 'prikogstreg-online-invitations' ); ?></label><br />
					<input type="datetime-local" id="pks-oi-rsvp-deadline" name="rsvp_deadline_utc" value="<?php echo esc_attr( (string) ( $project['rsvp_deadline_local'] ?? '' ) ); ?>" />
				</p>

				<p>
					<label for="pks-oi-venue-name"><?php esc_html_e( 'Venue name', 'prikogstreg-online-invitations' ); ?></label><br />
					<input type="text" id="pks-oi-venue-name" name="venue_name" value="<?php echo esc_attr( (string) ( $project['venue_name'] ?? '' ) ); ?>" />
				</p>

				<p>
					<label for="pks-oi-venue-address"><?php esc_html_e( 'Address line 1', 'prikogstreg-online-invitations' ); ?></label><br />
					<input type="text" id="pks-oi-venue-address" name="venue_address_line1" value="<?php echo esc_attr( (string) ( $project['venue_address_line1'] ?? '' ) ); ?>" />
				</p>

				<p>
					<label for="pks-oi-practical-info"><?php esc_html_e( 'Practical information', 'prikogstreg-online-invitations' ); ?></label><br />
					<textarea id="pks-oi-practical-info" name="practical_info" rows="4"><?php echo esc_textarea( (string) ( $project['practical_info'] ?? '' ) ); ?></textarea>
				</p>

				<p>
					<label for="pks-oi-organiser"><?php esc_html_e( 'Organiser display name', 'prikogstreg-online-invitations' ); ?></label><br />
					<input type="text" id="pks-oi-organiser" name="organiser_display_name" value="<?php echo esc_attr( (string) ( $project['organiser_display_name'] ?? '' ) ); ?>" />
				</p>

				<p>
					<label for="pks-oi-contact-email"><?php esc_html_e( 'Public contact email', 'prikogstreg-online-invitations' ); ?></label><br />
					<input type="email" id="pks-oi-contact-email" name="public_contact_email" value="<?php echo esc_attr( (string) ( $project['public_contact_email'] ?? '' ) ); ?>" />
				</p>

				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save event details', 'prikogstreg-online-invitations' ); ?></button></p>
			</form>
		<?php endif; ?>
	</section>
</div>
