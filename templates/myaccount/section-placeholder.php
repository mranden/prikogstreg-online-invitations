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
require __DIR__ . '/_section-ui.php';

$section_label = $sections[ $section ] ?? $section;

pks_oi_project_open( $notices, $section, $sections, $section_urls );
pks_oi_section_open( (string) $section_label );
pks_oi_render_empty_state(
	esc_html__( 'Coming soon', 'prikogstreg-online-invitations' ),
	esc_html__( 'This section is not available yet. It will be added in an upcoming release.', 'prikogstreg-online-invitations' )
);
pks_oi_section_close();
pks_oi_project_close();
