<?php
/**
 * Project design editor section.
 *
 * @package PrikOgStreg\OnlineInvitations
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/_helpers.php';
?>
<?php pks_oi_project_open( 'pks-oi-project--design' ); ?>
	<?php pks_oi_render_notices( $notices ); ?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<?php
	pks_oi_section_open(
		'pks-oi-design-title',
		__( 'Design', 'prikogstreg-online-invitations' ),
		__( 'Customise your invitation. Changes save automatically when you use the editor save action.', 'prikogstreg-online-invitations' )
	);
	?>

	<?php if ( ! empty( $editor_error ) ) : ?>
		<div class="pks-oi-empty-state" role="alert">
			<h4 class="pks-oi-empty-state__title"><?php esc_html_e( 'Design needs attention', 'prikogstreg-online-invitations' ); ?></h4>
			<p class="pks-oi-empty-state__message"><?php echo esc_html( (string) $editor_error ); ?></p>
		</div>
	<?php elseif ( '' === $editor_html ) : ?>
		<?php
		pks_oi_render_empty_state(
			__( 'Design editor unavailable', 'prikogstreg-online-invitations' ),
			__( 'The PDF Builder integration is not active for this product. Contact support if you expected a design editor here.', 'prikogstreg-online-invitations' )
		);
		?>
	<?php else : ?>
		<div
			id="pks-oi-editor"
			class="pks-oi-editor"
			data-pks-oi-rest-url="<?php echo esc_url( $rest_save_url ); ?>"
			data-pks-oi-rest-nonce="<?php echo esc_attr( $rest_nonce ); ?>"
			data-pks-oi-state-version="<?php echo esc_attr( (string) $state_version ); ?>"
			data-pks-oi-project-id="<?php echo esc_attr( (string) $project_id ); ?>"
		>
			<?php echo $editor_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<p class="pks-oi-design__save-status" id="pks-oi-save-status" aria-live="polite" hidden></p>
	<?php endif; ?>

	<?php pks_oi_section_close(); ?>
<?php pks_oi_project_close(); ?>
