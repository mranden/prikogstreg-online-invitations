<?php
/**
 * Public photo share link on invitation.
 *
 * @package PrikOgStreg\OnlineInvitations
 *
 * @var array<string, mixed> $photos
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$share_url = (string) ( $photos['share_url'] ?? '' );
?>
<?php if ( '' !== $share_url ) : ?>
	<p><?php esc_html_e( 'Share your photos from the event on the dedicated photo page.', 'prikogstreg-online-invitations' ); ?></p>
	<p>
		<a class="button" href="<?php echo esc_url( $share_url ); ?>">
			<?php esc_html_e( 'Open photo sharing page', 'prikogstreg-online-invitations' ); ?>
		</a>
	</p>
<?php endif; ?>
