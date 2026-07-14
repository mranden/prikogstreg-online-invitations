<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<p><?php printf( esc_html__( '%d wishlist items configured for this project.', 'prikogstreg-online-invitations' ), (int) ( $counts['wishlist'] ?? 0 ) ); ?></p>
<?php if ( '' !== (string) ( $project['external_wishlist_url'] ?? '' ) ) : ?>
	<p><?php esc_html_e( 'External wishlist URL is configured.', 'prikogstreg-online-invitations' ); ?></p>
<?php endif; ?>
