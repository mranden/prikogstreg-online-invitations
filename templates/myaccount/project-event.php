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
<?php pks_oi_project_open(); ?>
	<?php pks_oi_render_notices( $notices ); ?>
	<?php if ( $saved ) : ?>
		<?php pks_oi_render_notices( [ [ 'type' => 'success', 'message' => __( 'Event details saved.', 'prikogstreg-online-invitations' ) ] ] ); ?>
	<?php endif; ?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<?php
	pks_oi_section_open(
		'pks-oi-event-title',
		__( 'Event details', 'prikogstreg-online-invitations' ),
		__( 'Add the information guests will see on your invitation and RSVP page.', 'prikogstreg-online-invitations' )
	);
	?>

	<?php if ( ! $can_edit ) : ?>
		<?php
		pks_oi_render_empty_state(
			__( 'Editing disabled', 'prikogstreg-online-invitations' ),
			__( 'Event details cannot be edited for this project.', 'prikogstreg-online-invitations' )
		);
		?>
	<?php else : ?>
		<form method="post" action="" class="pks-oi-form pks-oi-event-form">
			<?php wp_nonce_field( \PrikOgStreg\OnlineInvitations\MyAccount\ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
			<input type="hidden" name="pks_oi_action" value="save_event" />

			<?php pks_oi_field_group_open( __( 'Basics', 'prikogstreg-online-invitations' ), __( 'How your event appears to guests.', 'prikogstreg-online-invitations' ) ); ?>
				<?php
				pks_oi_render_field(
					[
						'id'       => 'pks-oi-event-title-field',
						'name'     => 'event_title',
						'label'    => __( 'Event title', 'prikogstreg-online-invitations' ),
						'value'    => (string) ( $project['event_title'] ?? '' ),
						'required' => true,
						'wide'     => true,
					]
				);
				pks_oi_render_field(
					[
						'id'    => 'pks-oi-organiser',
						'name'  => 'organiser_display_name',
						'label' => __( 'Organiser display name', 'prikogstreg-online-invitations' ),
						'value' => (string) ( $project['organiser_display_name'] ?? '' ),
					]
				);
				pks_oi_render_field(
					[
						'id'    => 'pks-oi-contact-email',
						'name'  => 'public_contact_email',
						'label' => __( 'Public contact email', 'prikogstreg-online-invitations' ),
						'type'  => 'email',
						'value' => (string) ( $project['public_contact_email'] ?? '' ),
					]
				);
				?>
			<?php pks_oi_field_group_close(); ?>

			<?php pks_oi_field_group_open( __( 'When', 'prikogstreg-online-invitations' ), __( 'Dates are stored in your chosen timezone.', 'prikogstreg-online-invitations' ) ); ?>
				<?php
				pks_oi_render_field(
					[
						'id'    => 'pks-oi-timezone',
						'name'  => 'timezone',
						'label' => __( 'Timezone', 'prikogstreg-online-invitations' ),
						'value' => (string) ( $project['timezone'] ?? 'Europe/Copenhagen' ),
						'hint'  => __( 'Example: Europe/Copenhagen', 'prikogstreg-online-invitations' ),
						'wide'  => true,
					]
				);
				pks_oi_render_field(
					[
						'id'    => 'pks-oi-event-start',
						'name'  => 'event_start_utc',
						'label' => __( 'Event start (local)', 'prikogstreg-online-invitations' ),
						'type'  => 'datetime-local',
						'value' => (string) ( $project['event_start_local'] ?? '' ),
					]
				);
				pks_oi_render_field(
					[
						'id'    => 'pks-oi-event-end',
						'name'  => 'event_end_utc',
						'label' => __( 'Event end (local)', 'prikogstreg-online-invitations' ),
						'type'  => 'datetime-local',
						'value' => (string) ( $project['event_end_local'] ?? '' ),
					]
				);
				pks_oi_render_field(
					[
						'id'    => 'pks-oi-rsvp-deadline',
						'name'  => 'rsvp_deadline_utc',
						'label' => __( 'RSVP deadline (local)', 'prikogstreg-online-invitations' ),
						'type'  => 'datetime-local',
						'value' => (string) ( $project['rsvp_deadline_local'] ?? '' ),
						'wide'  => true,
					]
				);
				?>
			<?php pks_oi_field_group_close(); ?>

			<?php pks_oi_field_group_open( __( 'Where', 'prikogstreg-online-invitations' ) ); ?>
				<?php
				pks_oi_render_field(
					[
						'id'    => 'pks-oi-venue-name',
						'name'  => 'venue_name',
						'label' => __( 'Venue name', 'prikogstreg-online-invitations' ),
						'value' => (string) ( $project['venue_name'] ?? '' ),
					]
				);
				pks_oi_render_field(
					[
						'id'    => 'pks-oi-venue-address',
						'name'  => 'venue_address_line1',
						'label' => __( 'Address line 1', 'prikogstreg-online-invitations' ),
						'value' => (string) ( $project['venue_address_line1'] ?? '' ),
					]
				);
				?>
			<?php pks_oi_field_group_close(); ?>

			<?php pks_oi_field_group_open( __( 'Extra information', 'prikogstreg-online-invitations' ) ); ?>
				<?php
				pks_oi_render_field(
					[
						'id'    => 'pks-oi-practical-info',
						'name'  => 'practical_info',
						'label' => __( 'Practical information', 'prikogstreg-online-invitations' ),
						'type'  => 'textarea',
						'value' => (string) ( $project['practical_info'] ?? '' ),
						'wide'  => true,
						'rows'  => 4,
					]
				);
				?>
			<?php pks_oi_field_group_close(); ?>

			<?php pks_oi_form_actions( __( 'Save event details', 'prikogstreg-online-invitations' ), true ); ?>
		</form>
	<?php endif; ?>

	<?php pks_oi_section_close(); ?>
<?php pks_oi_project_close(); ?>
