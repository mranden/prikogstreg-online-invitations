<?php
/**
 * Public invitation link and design access.
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

$public_url     = (string) ( $public_url ?? '' );
$is_public_live = ! empty( $is_public_live );
$design_url     = Endpoints::project_url( $project_id, ProjectSections::DESIGN );
?>
<?php pks_oi_project_open(); ?>
	<?php pks_oi_render_notices( $notices ); ?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<?php
	pks_oi_section_open(
		'pks-oi-preview-title',
		__( 'Preview', 'prikogstreg-online-invitations' ),
		__( 'Open your public invitation link or view your imported design.', 'prikogstreg-online-invitations' )
	);
	?>

	<div class="pks-oi-preview__toolbar">
		<?php if ( '' !== $public_url ) : ?>
			<div class="pks-oi-field pks-oi-field--wide">
				<label class="pks-oi-field__label" for="pks-oi-public-url"><?php esc_html_e( 'Public URL', 'prikogstreg-online-invitations' ); ?></label>
				<input
					id="pks-oi-public-url"
					type="text"
					readonly
					class="pks-oi-field__control"
					value="<?php echo esc_attr( $public_url ); ?>"
					onclick="this.select();"
				/>
			</div>
			<a class="button button-primary" href="<?php echo esc_url( $public_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Preview invitation', 'prikogstreg-online-invitations' ); ?>
			</a>
		<?php elseif ( $is_public_live ) : ?>
			<p class="pks-oi-field__hint"><?php esc_html_e( 'Your public link is being prepared. Refresh this page in a moment.', 'prikogstreg-online-invitations' ); ?></p>
		<?php else : ?>
			<p class="pks-oi-field__hint"><?php esc_html_e( 'Complete your design and event details to activate your public invitation link.', 'prikogstreg-online-invitations' ); ?></p>
		<?php endif; ?>

		<a class="button" href="<?php echo esc_url( $design_url ); ?>"><?php esc_html_e( 'View design', 'prikogstreg-online-invitations' ); ?></a>
	</div>

	<?php if ( '' !== $envelope_preset ) : ?>
		<p class="pks-oi-preview__meta"><?php printf( esc_html__( 'Envelope: %s', 'prikogstreg-online-invitations' ), esc_html( (string) $envelope_preset ) ); ?></p>
	<?php endif; ?>

	<?php pks_oi_section_close(); ?>
<?php pks_oi_project_close(); ?>
