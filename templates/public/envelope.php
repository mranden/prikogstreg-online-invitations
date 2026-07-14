<?php
/**
 * Animated envelope shell with accessible open control and noscript fallback.
 *
 * @package PrikOgStreg\OnlineInvitations
 * @version 1.0.0
 *
 * @var \PrikOgStreg\OnlineInvitations\Public\EnvelopeViewModel $envelope_view
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$view = $envelope_view;
?>
<div
	class="pks-oi-envelope pks-oi-envelope--<?php echo esc_attr( $view->envelope_preset ); ?> pks-oi-envelope--bg-<?php echo esc_attr( $view->background_preset ); ?>"
	data-envelope-state="closed"
	data-pks-oi-link-type="<?php echo esc_attr( $view->link_type ); ?>"
	data-pks-oi-track-opens="<?php echo $view->track_opens ? '1' : '0'; ?>"
	<?php if ( '' !== $view->session_storage_key ) : ?>
		data-pks-oi-session-key="<?php echo esc_attr( $view->session_storage_key ); ?>"
	<?php endif; ?>
>
	<div
		class="pks-oi-envelope__stage"
		aria-labelledby="pks-oi-envelope-title"
		aria-describedby="pks-oi-envelope-desc"
	>
		<p id="pks-oi-envelope-desc" class="pks-oi-sr-only">
			<?php esc_html_e( 'A digital invitation envelope. Use the button below to open it and read the invitation.', 'prikogstreg-online-invitations' ); ?>
		</p>

		<div class="pks-oi-envelope__scene">
			<div class="pks-oi-envelope__shell" aria-hidden="true">
				<div class="pks-oi-envelope__pocket"></div>
				<div class="pks-oi-envelope__flap"></div>
			</div>

			<div class="pks-oi-envelope__letter">
				<div class="pks-oi-envelope__card">
					<?php if ( '' !== $view->envelope_image_url ) : ?>
						<img
							class="pks-oi-envelope__card-image"
							src="<?php echo esc_url( $view->envelope_image_url ); ?>"
							alt=""
							loading="eager"
							decoding="async"
							<?php if ( $view->envelope_image_width > 0 && $view->envelope_image_height > 0 ) : ?>
								width="<?php echo esc_attr( (string) $view->envelope_image_width ); ?>"
								height="<?php echo esc_attr( (string) $view->envelope_image_height ); ?>"
							<?php endif; ?>
						/>
					<?php else : ?>
						<div class="pks-oi-envelope__card-fallback" aria-hidden="true"></div>
					<?php endif; ?>
					<p class="pks-oi-envelope__addressee" id="pks-oi-envelope-title"><?php echo esc_html( $view->addressee_label ); ?></p>
					<?php if ( '' !== $view->event_title ) : ?>
						<p class="pks-oi-envelope__event"><?php echo esc_html( $view->event_title ); ?></p>
					<?php endif; ?>
				</div>

				<div class="pks-oi-envelope__letter-content">
					<div class="pks-oi-envelope__invitation">
						<?php require __DIR__ . '/poster.php'; ?>
					</div>
				</div>
			</div>
		</div>

		<button
			type="button"
			class="pks-oi-envelope__open"
			id="pks-oi-open-invitation"
			aria-controls="pks-oi-invitation-content"
			aria-expanded="false"
			aria-label="<?php esc_attr_e( 'Open invitation', 'prikogstreg-online-invitations' ); ?>"
		>
			<?php esc_html_e( 'Open invitation', 'prikogstreg-online-invitations' ); ?>
		</button>
	</div>

	<div
		class="pks-oi-envelope__content"
		id="pks-oi-invitation-content"
		tabindex="-1"
		hidden
		inert
	>
		<div class="pks-oi-envelope__revealed-poster">
			<h2 class="pks-oi-sr-only" id="pks-oi-invitation-heading" tabindex="-1"><?php esc_html_e( 'Invitation', 'prikogstreg-online-invitations' ); ?></h2>
			<?php require __DIR__ . '/poster.php'; ?>
		</div>

		<?php foreach ( $view->sections as $section ) : ?>
			<?php if ( empty( $section['enabled'] ) ) : ?>
				<?php continue; ?>
			<?php endif; ?>
			<section class="pks-oi-public-section pks-oi-public-section--<?php echo esc_attr( (string) $section['key'] ); ?>" aria-label="<?php echo esc_attr( (string) $section['label'] ); ?>">
				<h2><?php echo esc_html( (string) $section['label'] ); ?></h2>
				<?php if ( 'event' === (string) ( $section['key'] ?? '' ) ) : ?>
					<?php
					$event_details = $view->event_details;
					require __DIR__ . '/partials/event-details.php';
					?>
				<?php elseif ( 'rsvp' === (string) ( $section['key'] ?? '' ) ) : ?>
					<?php
					$rsvp_form = $view->rsvp_form;
					require __DIR__ . '/rsvp-form.php';
					?>
				<?php elseif ( 'wishlist' === (string) ( $section['key'] ?? '' ) ) : ?>
					<?php
					$wishlist = $view->wishlist;
					require __DIR__ . '/wishlist.php';
					?>
				<?php elseif ( 'photos' === (string) ( $section['key'] ?? '' ) ) : ?>
					<?php
					$photos = $view->photos;
					require __DIR__ . '/photos.php';
					?>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	</div>

	<noscript>
		<style>
			.pks-oi-envelope__stage { display: none !important; }
			#pks-oi-invitation-content[hidden] { display: block !important; }
			#pks-oi-invitation-content[inert] { pointer-events: auto; }
			.pks-oi-envelope__open { display: none !important; }
		</style>
	</noscript>
</div>
