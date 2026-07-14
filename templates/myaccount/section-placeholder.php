<?php
/**
 * Honest placeholder for sections not yet implemented.
 *
 * @package PrikOgStreg\OnlineInvitations
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/_helpers.php';

$section_label = $sections[ $section ] ?? $section;
?>
<div class="pks-oi pks-oi-myaccount pks-oi-project">
	<?php pks_oi_render_notices( $notices ); ?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<section aria-labelledby="pks-oi-section-title">
		<h3 id="pks-oi-section-title"><?php echo esc_html( (string) $section_label ); ?></h3>
		<p><?php esc_html_e( 'This section is not available yet. It will be added in an upcoming release.', 'prikogstreg-online-invitations' ); ?></p>
	</section>
</div>
