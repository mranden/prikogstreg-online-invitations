<?php
/**
 * Authenticated draft preview (no open tracking).
 *
 * @package PrikOgStreg\OnlineInvitations
 *
 * @var string $preview_html
 * @var string $envelope_preset
 * @var bool   $track_opens
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/_helpers.php';
?>
<div class="pks-oi pks-oi-myaccount pks-oi-project">
	<?php pks_oi_render_notices( $notices ); ?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<section class="pks-oi-preview" aria-labelledby="pks-oi-preview-title">
		<h3 id="pks-oi-preview-title"><?php esc_html_e( 'Preview', 'prikogstreg-online-invitations' ); ?></h3>
		<p class="pks-oi-preview__note"><?php esc_html_e( 'This is a private draft preview. Opens are not tracked.', 'prikogstreg-online-invitations' ); ?></p>

		<?php if ( '' !== $envelope_preset ) : ?>
			<p><?php printf( esc_html__( 'Envelope: %s', 'prikogstreg-online-invitations' ), esc_html( $envelope_preset ) ); ?></p>
		<?php endif; ?>

		<div class="pks-oi-preview__frame" data-track-opens="<?php echo $track_opens ? '1' : '0'; ?>">
			<?php if ( '' === $preview_html ) : ?>
				<p><?php esc_html_e( 'Preview content is not available yet.', 'prikogstreg-online-invitations' ); ?></p>
			<?php else : ?>
				<div class="pks-oi-preview__html">
					<?php echo $preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- draft builder HTML ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
</div>
