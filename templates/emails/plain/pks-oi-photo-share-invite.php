<?php
/**
 * Plain photo share invite e-mail.
 *
 * @package PrikOgStreg\OnlineInvitations
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo esc_html__( 'You are invited to share photos from the event.', 'prikogstreg-online-invitations' ) . "\n\n";
echo esc_url( (string) ( $context['photo_share_url'] ?? '' ) ) . "\n\n";
echo esc_html__( 'You will need the photo code from the organiser to upload photos.', 'prikogstreg-online-invitations' ) . "\n";
