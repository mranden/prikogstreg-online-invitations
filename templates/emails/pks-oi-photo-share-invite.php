<?php
/**
 * Photo share invite e-mail.
 *
 * @package PrikOgStreg\OnlineInvitations
 *
 * @var array<string, mixed> $project
 * @var array<string, mixed>|null $guest
 * @var string $photo_share_url
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$event_title = trim( (string) ( $project['event_title'] ?? '' ) );
$photo_share_url = (string) ( $context['photo_share_url'] ?? '' );
$guest = is_array( $context['guest'] ?? null ) ? $context['guest'] : null;
?>
<p><?php esc_html_e( 'You are invited to share photos from the event.', 'prikogstreg-online-invitations' ); ?></p>
<?php if ( '' !== $event_title ) : ?>
	<p><strong><?php echo esc_html( $event_title ); ?></strong></p>
<?php endif; ?>
<p>
	<a href="<?php echo esc_url( (string) ( $photo_share_url ?? '' ) ); ?>">
		<?php esc_html_e( 'Open the photo sharing page', 'prikogstreg-online-invitations' ); ?>
	</a>
</p>
<p><?php esc_html_e( 'You will need the photo code from the organiser to upload photos.', 'prikogstreg-online-invitations' ); ?></p>
