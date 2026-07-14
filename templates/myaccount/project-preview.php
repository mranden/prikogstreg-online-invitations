<?php
/**
 * Authenticated draft preview (no open tracking).
 *
 * @package PrikOgStreg\OnlineInvitations
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/_helpers.php';

use PrikOgStreg\OnlineInvitations\MyAccount\Endpoints;
use PrikOgStreg\OnlineInvitations\MyAccount\ProjectSections;
?>
<?php pks_oi_project_open(); ?>
	<?php pks_oi_render_notices( $notices ); ?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<?php
	pks_oi_section_open(
		'pks-oi-preview-title',
		__( 'Preview', 'prikogstreg-online-invitations' ),
		__( 'Private draft preview — opens are not tracked.', 'prikogstreg-online-invitations' )
	);
	?>

	<div class="pks-oi-preview__toolbar">
		<?php pks_oi_render_badge( __( 'Draft preview', 'prikogstreg-online-invitations' ), 'neutral' ); ?>
		<?php if ( '' !== $envelope_preset ) : ?>
			<?php pks_oi_render_badge( sprintf( __( 'Envelope: %s', 'prikogstreg-online-invitations' ), $envelope_preset ), 'neutral' ); ?>
		<?php endif; ?>
		<a class="button" href="<?php echo esc_url( Endpoints::project_url( $project_id, ProjectSections::DESIGN ) ); ?>"><?php esc_html_e( 'Edit design', 'prikogstreg-online-invitations' ); ?></a>
		<a class="button" href="<?php echo esc_url( Endpoints::project_url( $project_id, ProjectSections::PUBLISH ) ); ?>"><?php esc_html_e( 'Go to publish', 'prikogstreg-online-invitations' ); ?></a>
		<button type="button" class="button" data-pks-oi-preview-toggle><?php esc_html_e( 'Toggle full width', 'prikogstreg-online-invitations' ); ?></button>
	</div>

	<div class="pks-oi-preview__device" data-pks-oi-preview-device>
		<?php if ( '' === $preview_html ) : ?>
			<?php
			pks_oi_render_empty_state(
				__( 'Preview not ready', 'prikogstreg-online-invitations' ),
				__( 'Complete your design and event details to generate a preview.', 'prikogstreg-online-invitations' ),
				[ 'label' => __( 'Go to design', 'prikogstreg-online-invitations' ), 'url' => Endpoints::project_url( $project_id, ProjectSections::DESIGN ) ]
			);
			?>
		<?php else : ?>
			<div class="pks-oi-preview__html" data-track-opens="<?php echo $track_opens ? '1' : '0'; ?>">
				<?php echo $preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		<?php endif; ?>
	</div>

	<?php pks_oi_section_close(); ?>
<?php pks_oi_project_close(); ?>
