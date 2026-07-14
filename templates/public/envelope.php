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
	data-pks-oi-link-type="<?php echo esc_attr( $view->link_type ); ?>"
	data-pks-oi-track-opens="<?php echo $view->track_opens ? '1' : '0'; ?>"
>
	<div class="pks-oi-envelope__stage">
		<div class="pks-oi-envelope__card" aria-hidden="true">
			<p class="pks-oi-envelope__addressee"><?php echo esc_html( $view->addressee_label ); ?></p>
			<?php if ( '' !== $view->event_title ) : ?>
				<p class="pks-oi-envelope__event"><?php echo esc_html( $view->event_title ); ?></p>
			<?php endif; ?>
		</div>

		<button
			type="button"
			class="pks-oi-envelope__open"
			id="pks-oi-open-invitation"
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
	>
		<div class="pks-oi-envelope__invitation bpp-public-invitation">
			<?php echo $view->invitation_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized published snapshot ?>
		</div>

		<?php foreach ( $view->sections as $section ) : ?>
			<?php if ( empty( $section['enabled'] ) ) : ?>
				<?php continue; ?>
			<?php endif; ?>
			<section class="pks-oi-public-section pks-oi-public-section--<?php echo esc_attr( (string) $section['key'] ); ?>" aria-label="<?php echo esc_attr( (string) $section['label'] ); ?>">
				<h2><?php echo esc_html( (string) $section['label'] ); ?></h2>
				<?php if ( 'rsvp' === (string) ( $section['key'] ?? '' ) ) : ?>
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
				<?php else : ?>
					<p class="pks-oi-public-section__placeholder"><?php esc_html_e( 'This section will be available in a later update.', 'prikogstreg-online-invitations' ); ?></p>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	</div>

	<noscript>
		<style>
			#pks-oi-invitation-content[hidden] { display: block !important; }
			.pks-oi-envelope__open { display: none; }
		</style>
	</noscript>
</div>
