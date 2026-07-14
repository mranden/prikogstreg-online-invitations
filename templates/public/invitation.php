<?php
/**
 * Public invitation page.
 *
 * @package PrikOgStreg\OnlineInvitations
 *
 * @var \PrikOgStreg\OnlineInvitations\Public\EnvelopeViewModel $view
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
	<title><?php echo esc_html( '' !== $view->event_title ? $view->event_title : __( 'Invitation', 'prikogstreg-online-invitations' ) ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'pks-oi-public pks-oi-public-invitation' ); ?>>
	<main id="pks-oi-public-main" class="pks-oi-public__main">
		<?php
		$envelope_view = $view;
		require __DIR__ . '/envelope.php';
		?>
	</main>
	<?php wp_footer(); ?>
</body>
</html>
