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

$share_url      = (string) ( $photos['share_url'] ?? '' );
$is_sample      = ! empty( $photos['is_sample'] );
$qr_svg         = (string) ( $photos['qr_svg'] ?? '' );
$gallery_items  = is_array( $photos['gallery_items'] ?? null ) ? $photos['gallery_items'] : [];
?>
<div class="pks-oi-photos<?php echo $is_sample ? ' pks-oi-photos--sample' : ''; ?>">
	<?php if ( $is_sample ) : ?>
		<p class="pks-oi-photos__sample-note" role="note">
			<?php esc_html_e( 'Example only — photo sharing and QR codes are configured on each invitation. The QR code below is for demonstration and does not work.', 'prikogstreg-online-invitations' ); ?>
		</p>
		<p><?php esc_html_e( 'Guests can upload photos from the event using a photo code. Hosts can share a link or print a QR code for easy access.', 'prikogstreg-online-invitations' ); ?></p>

		<?php if ( '' !== $share_url ) : ?>
			<p class="pks-oi-photos__example-link">
				<label class="pks-oi-photos__example-label" for="pks-oi-sample-photo-url"><?php esc_html_e( 'Example upload link', 'prikogstreg-online-invitations' ); ?></label><br />
				<input id="pks-oi-sample-photo-url" type="text" readonly class="pks-oi-photos__example-input" value="<?php echo esc_attr( $share_url ); ?>" onclick="this.select();" />
			</p>
		<?php endif; ?>

		<?php if ( '' !== $qr_svg ) : ?>
			<div class="pks-oi-photos__qr-wrap">
				<h3 class="pks-oi-photos__qr-title"><?php esc_html_e( 'Example QR code', 'prikogstreg-online-invitations' ); ?></h3>
				<div class="pks-oi-photos__qr" aria-hidden="true">
					<?php echo $qr_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted SVG from PhotoShareQrService ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( [] !== $gallery_items ) : ?>
			<div class="pks-oi-photos__gallery-wrap">
				<h3 class="pks-oi-photos__gallery-title"><?php esc_html_e( 'Example uploaded photos', 'prikogstreg-online-invitations' ); ?></h3>
				<ul class="pks-oi-photos__gallery">
					<?php foreach ( $gallery_items as $photo ) : ?>
						<li class="pks-oi-photos__gallery-item">
							<img
								src="<?php echo esc_url( (string) ( $photo['image_url'] ?? '' ) ); ?>"
								alt="<?php echo esc_attr( (string) ( $photo['caption'] ?? '' ) ); ?>"
								loading="lazy"
								decoding="async"
							/>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	<?php elseif ( '' !== $share_url ) : ?>
		<p><?php esc_html_e( 'Share your photos from the event on the dedicated photo page.', 'prikogstreg-online-invitations' ); ?></p>
		<p>
			<a class="button" href="<?php echo esc_url( $share_url ); ?>">
				<?php esc_html_e( 'Open photo sharing page', 'prikogstreg-online-invitations' ); ?>
			</a>
		</p>
	<?php endif; ?>
</div>
