<?php
/**
 * Uniform unavailable invitation response.
 *
 * @package PrikOgStreg\OnlineInvitations
 *
 * @var string $message
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex,nofollow" />
	<title><?php esc_html_e( 'Invitation unavailable', 'prikogstreg-online-invitations' ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'pks-oi-public pks-oi-public-unavailable' ); ?>>
	<main class="pks-oi-public__main pks-oi-public__unavailable">
		<h1><?php esc_html_e( 'Invitation unavailable', 'prikogstreg-online-invitations' ); ?></h1>
		<p><?php echo esc_html( $message ); ?></p>
	</main>
	<?php wp_footer(); ?>
</body>
</html>
