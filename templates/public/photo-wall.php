<?php
/**
 * Public photo wall — approved guest photos without upload flow.
 *
 * @package PrikOgStreg\OnlineInvitations
 *
 * @var array<string, mixed> $project
 * @var string               $event_title
 * @var string               $organiser_name
 * @var string               $rest_base
 * @var string               $rest_nonce
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex,nofollow" />
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'pks-oi-public pks-oi-photo-wall-page' ); ?>>
<main
	class="pks-oi-photo-wall"
	data-pks-oi-photo-wall
	data-rest-base="<?php echo esc_attr( $rest_base ); ?>"
	data-rest-nonce="<?php echo esc_attr( $rest_nonce ); ?>"
>
	<header class="pks-oi-photo-wall__hero">
		<p class="pks-oi-photo-wall__eyebrow"><?php esc_html_e( 'Photo wall', 'prikogstreg-online-invitations' ); ?></p>
		<?php if ( '' !== $event_title ) : ?>
			<h1><?php echo esc_html( $event_title ); ?></h1>
		<?php else : ?>
			<h1><?php esc_html_e( 'Event photos', 'prikogstreg-online-invitations' ); ?></h1>
		<?php endif; ?>
		<?php if ( '' !== $organiser_name ) : ?>
			<p class="pks-oi-photo-wall__organiser">
				<?php
				printf(
					/* translators: %s: organiser display name */
					esc_html__( 'Hosted by %s', 'prikogstreg-online-invitations' ),
					esc_html( $organiser_name )
				);
				?>
			</p>
		<?php endif; ?>
	</header>

	<p class="pks-oi-photo-wall__intro"><?php esc_html_e( 'Photos shared by guests at this event.', 'prikogstreg-online-invitations' ); ?></p>

	<div class="pks-oi-photo-wall__panel">
		<div
			class="pks-oi-photo-wall__grid"
			data-pks-oi-photo-gallery
			data-gallery-path="/wall/gallery"
			aria-live="polite"
		></div>
		<p class="pks-oi-photo-wall__load-more-wrap"><button type="button" class="pks-oi-photo-wall__load-more" data-pks-oi-photo-gallery-more hidden><?php esc_html_e( 'Load more', 'prikogstreg-online-invitations' ); ?></button></p>
		<p class="pks-oi-photo-wall__empty" data-pks-oi-photo-wall-empty hidden><?php esc_html_e( 'No photos yet. Check back soon.', 'prikogstreg-online-invitations' ); ?></p>
	</div>
</main>
<?php wp_footer(); ?>
</body>
</html>
