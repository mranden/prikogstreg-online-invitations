<?php
/**
 * Dedicated event photo share landing page.
 *
 * @package PrikOgStreg\OnlineInvitations
 *
 * @var array<string, mixed> $project
 * @var string               $event_title
 * @var string               $organiser_name
 * @var bool                 $authorized
 * @var bool                 $upload_open
 * @var bool                 $gallery_public
 * @var bool                 $auto_approve
 * @var string               $wall_url
 * @var string               $rest_base
 * @var string               $rest_nonce
 * @var int                  $max_files
 * @var string               $moderation_notice
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$code_hint  = ! empty( $auto_approve )
	? __( 'Approved photos appear on the photo wall immediately.', 'prikogstreg-online-invitations' )
	: __( 'Photos are reviewed by the organiser before they appear on the wall.', 'prikogstreg-online-invitations' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex,nofollow" />
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'pks-oi-public pks-oi-photo-share-page' ); ?>>
<main
	class="pks-oi-photo-share"
	data-pks-oi-photo-share
	data-authorized="<?php echo $authorized ? '1' : '0'; ?>"
	data-rest-base="<?php echo esc_attr( $rest_base ); ?>"
	data-rest-nonce="<?php echo esc_attr( $rest_nonce ); ?>"
>
	<header class="pks-oi-photo-share__hero">
		<p class="pks-oi-photo-share__eyebrow"><?php esc_html_e( 'Guest photos', 'prikogstreg-online-invitations' ); ?></p>
		<?php if ( '' !== $event_title ) : ?>
			<h1><?php echo esc_html( $event_title ); ?></h1>
		<?php else : ?>
			<h1><?php esc_html_e( 'Event photos', 'prikogstreg-online-invitations' ); ?></h1>
		<?php endif; ?>
		<?php if ( '' !== $organiser_name ) : ?>
			<p class="pks-oi-photo-share__organiser">
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

	<p class="pks-oi-photo-share__intro">
		<?php esc_html_e( 'Share your photos from the event. Enter the photo code from the organiser to upload.', 'prikogstreg-online-invitations' ); ?>
	</p>

	<p class="pks-oi-status pks-oi-photo-share__status" data-pks-oi-photo-share-status role="status" aria-live="polite" hidden></p>

	<?php if ( ! $authorized ) : ?>
		<section class="pks-oi-photo-share__card" aria-labelledby="pks-oi-photo-code-title">
			<h2 id="pks-oi-photo-code-title"><?php esc_html_e( 'Enter photo code', 'prikogstreg-online-invitations' ); ?></h2>
			<p class="pks-oi-photo-share__card-lead"><?php esc_html_e( 'Use the photo code shared by the organiser to unlock uploads.', 'prikogstreg-online-invitations' ); ?></p>
			<form class="pks-oi-photo-share__code-form" data-pks-oi-photo-code-form novalidate>
				<p>
					<label for="pks-oi-photo-code"><?php esc_html_e( 'Photo code', 'prikogstreg-online-invitations' ); ?></label>
					<input
						type="password"
						id="pks-oi-photo-code"
						data-pks-oi-photo-code
						autocomplete="off"
						placeholder="<?php esc_attr_e( 'Enter code', 'prikogstreg-online-invitations' ); ?>"
						required
					/>
				</p>
				<p>
					<button type="submit" class="pks-oi-photo-share__submit"><?php esc_html_e( 'Continue', 'prikogstreg-online-invitations' ); ?></button>
				</p>
			</form>
			<p class="pks-oi-photo-share__hint"><?php echo esc_html( $code_hint ); ?></p>
		</section>
	<?php else : ?>
		<section class="pks-oi-photo-share__card pks-oi-photo-share__upload" aria-labelledby="pks-oi-photo-upload-title">
			<h2 id="pks-oi-photo-upload-title"><?php esc_html_e( 'Upload photos', 'prikogstreg-online-invitations' ); ?></h2>
			<?php if ( $upload_open ) : ?>
				<div
					class="pks-oi-photo-share__uploader"
					data-pks-oi-photo-uploader
					data-rest-base="<?php echo esc_attr( $rest_base ); ?>"
					data-rest-nonce="<?php echo esc_attr( $rest_nonce ); ?>"
					data-max-files="<?php echo esc_attr( (string) $max_files ); ?>"
				>
					<p>
						<label for="pks-oi-photo-share-name"><?php esc_html_e( 'Your name', 'prikogstreg-online-invitations' ); ?></label>
						<input type="text" id="pks-oi-photo-share-name" data-pks-oi-photo-share-name placeholder="<?php esc_attr_e( 'Your name', 'prikogstreg-online-invitations' ); ?>" required />
					</p>
					<p>
						<label for="pks-oi-photo-share-input"><?php esc_html_e( 'Choose photos (JPEG, PNG, or WebP)', 'prikogstreg-online-invitations' ); ?></label>
						<input
							type="file"
							id="pks-oi-photo-share-input"
							data-pks-oi-photo-share-input
							accept="image/jpeg,image/png,image/webp"
							multiple
						/>
					</p>
					<ul class="pks-oi-photo-share__previews" data-pks-oi-photo-previews hidden></ul>
					<p>
						<label class="pks-oi-photo-share__consent">
							<input type="checkbox" data-pks-oi-photo-consent required />
							<span><?php esc_html_e( 'I confirm that I may share these photos and understand the organiser may approve, reject, or delete them.', 'prikogstreg-online-invitations' ); ?></span>
						</label>
					</p>
					<p>
						<button type="button" class="pks-oi-photo-share__submit" data-pks-oi-photo-share-upload><?php esc_html_e( 'Upload photos', 'prikogstreg-online-invitations' ); ?></button>
					</p>
					<p class="pks-oi-photo-share__hint"><?php echo esc_html( $moderation_notice ); ?></p>
				</div>
			<?php else : ?>
				<p class="pks-oi-photo-share__hint"><?php esc_html_e( 'Photo uploads are closed for this event.', 'prikogstreg-online-invitations' ); ?></p>
			<?php endif; ?>
		</section>

		<?php if ( $gallery_public ) : ?>
			<section class="pks-oi-photo-share__card pks-oi-photo-share__gallery" aria-labelledby="pks-oi-photo-gallery-title">
				<h2 id="pks-oi-photo-gallery-title"><?php esc_html_e( 'Event photo gallery', 'prikogstreg-online-invitations' ); ?></h2>
				<div
					class="pks-oi-photo-share__gallery-grid"
					data-pks-oi-photo-gallery
					data-gallery-path="/gallery"
					data-rest-base="<?php echo esc_attr( $rest_base ); ?>"
					data-rest-nonce="<?php echo esc_attr( $rest_nonce ); ?>"
				></div>
				<p class="pks-oi-photo-wall__load-more-wrap"><button type="button" class="pks-oi-photo-wall__load-more" data-pks-oi-photo-gallery-more hidden><?php esc_html_e( 'Load more', 'prikogstreg-online-invitations' ); ?></button></p>
			</section>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $gallery_public && '' !== (string) ( $wall_url ?? '' ) ) : ?>
		<aside class="pks-oi-photo-share__wall-card" aria-labelledby="pks-oi-photo-wall-link-title">
			<h2 id="pks-oi-photo-wall-link-title"><?php esc_html_e( 'Photo wall', 'prikogstreg-online-invitations' ); ?></h2>
			<p><?php esc_html_e( 'View all shared photos — no code needed.', 'prikogstreg-online-invitations' ); ?></p>
			<a class="pks-oi-photo-share__wall-btn" href="<?php echo esc_url( (string) $wall_url ); ?>"><?php esc_html_e( 'Open photo wall', 'prikogstreg-online-invitations' ); ?></a>
		</aside>
	<?php endif; ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
