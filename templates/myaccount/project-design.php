<?php
/**
 * Project design preview (read-only).
 *
 * @package PrikOgStreg\OnlineInvitations
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/_helpers.php';

$design_html                   = (string) ( $design_html ?? '' );
$design_error                  = (string) ( $design_error ?? '' );
$design_uses_template_fallback = ! empty( $design_uses_template_fallback );
$preview_html                  = (string) ( $preview_html ?? $design_html );
?>
<?php pks_oi_project_open( 'pks-oi-project--design' ); ?>
	<?php pks_oi_render_notices( $notices ); ?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<?php
	pks_oi_section_open(
		'pks-oi-design-title',
		__( 'Design', 'prikogstreg-online-invitations' ),
		__( 'Your invitation design from your order. To change text or images, use the order customizer in the shop admin.', 'prikogstreg-online-invitations' )
	);
	?>

	<?php if ( '' !== $design_error ) : ?>
		<div class="pks-oi-empty-state" role="alert">
			<h4 class="pks-oi-empty-state__title"><?php esc_html_e( 'Design needs attention', 'prikogstreg-online-invitations' ); ?></h4>
			<p class="pks-oi-empty-state__message"><?php echo esc_html( $design_error ); ?></p>
		</div>
	<?php elseif ( '' === $preview_html ) : ?>
		<?php
		pks_oi_render_empty_state(
			__( 'Design preview unavailable', 'prikogstreg-online-invitations' ),
			__( 'We could not load a preview for this design. Contact support if you expected to see your invitation here.', 'prikogstreg-online-invitations' )
		);
		?>
	<?php else : ?>
		<?php if ( $design_uses_template_fallback ) : ?>
			<p class="pks-oi-field__hint" role="status">
				<?php esc_html_e( 'No custom design was saved with your order. This preview uses the default template.', 'prikogstreg-online-invitations' ); ?>
			</p>
		<?php endif; ?>
		<?php require __DIR__ . '/partials/poster-preview-frame.php'; ?>
	<?php endif; ?>

	<?php pks_oi_section_close(); ?>
<?php pks_oi_project_close(); ?>
