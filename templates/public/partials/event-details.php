<?php
/**
 * Structured event information for public guests.
 *
 * @package PrikOgStreg\OnlineInvitations
 *
 * @var array<string, mixed> $event_details
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$details = $event_details;
if ( empty( $details['has_content'] ) ) {
	return;
}
?>
<dl class="pks-oi-event-details__list">
	<?php if ( '' !== (string) ( $details['date_label'] ?? '' ) ) : ?>
		<div class="pks-oi-event-details__item">
			<dt><?php esc_html_e( 'Date', 'prikogstreg-online-invitations' ); ?></dt>
			<dd><time><?php echo esc_html( (string) $details['date_label'] ); ?></time></dd>
		</div>
	<?php endif; ?>

	<?php if ( '' !== (string) ( $details['time_label'] ?? '' ) ) : ?>
		<div class="pks-oi-event-details__item">
			<dt><?php esc_html_e( 'Time', 'prikogstreg-online-invitations' ); ?></dt>
			<dd><time><?php echo esc_html( (string) $details['time_label'] ); ?></time></dd>
		</div>
	<?php endif; ?>

	<?php if ( '' !== (string) ( $details['venue_name'] ?? '' ) ) : ?>
		<div class="pks-oi-event-details__item">
			<dt><?php esc_html_e( 'Location', 'prikogstreg-online-invitations' ); ?></dt>
			<dd><?php echo esc_html( (string) $details['venue_name'] ); ?></dd>
		</div>
	<?php endif; ?>

	<?php
	$address_lines = is_array( $details['address_lines'] ?? null ) ? $details['address_lines'] : [];
	if ( [] !== $address_lines ) :
		?>
		<div class="pks-oi-event-details__item">
			<dt><?php esc_html_e( 'Address', 'prikogstreg-online-invitations' ); ?></dt>
			<dd>
				<address>
					<?php foreach ( $address_lines as $line ) : ?>
						<?php echo esc_html( (string) $line ); ?><br />
					<?php endforeach; ?>
				</address>
			</dd>
		</div>
	<?php endif; ?>

	<?php if ( '' !== (string) ( $details['maps_url'] ?? '' ) ) : ?>
		<div class="pks-oi-event-details__item">
			<dt><?php esc_html_e( 'Directions', 'prikogstreg-online-invitations' ); ?></dt>
			<dd>
				<a href="<?php echo esc_url( (string) $details['maps_url'] ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Open in Google Maps', 'prikogstreg-online-invitations' ); ?>
				</a>
			</dd>
		</div>
	<?php endif; ?>

	<?php if ( '' !== (string) ( $details['practical_info'] ?? '' ) ) : ?>
		<div class="pks-oi-event-details__item">
			<dt><?php esc_html_e( 'Practical information', 'prikogstreg-online-invitations' ); ?></dt>
			<dd><?php echo esc_html( (string) $details['practical_info'] ); ?></dd>
		</div>
	<?php endif; ?>
</dl>
