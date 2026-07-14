<?php
/**
 * Project overview shell.
 *
 * @package PrikOgStreg\OnlineInvitations
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/_helpers.php';

$expires_label = '' !== (string) ( $project['expires_at_utc'] ?? '' )
	? pks_oi_format_datetime_display( (string) $project['expires_at_utc'] )
	: __( 'Pending event date', 'prikogstreg-online-invitations' );
?>
<?php pks_oi_project_open(); ?>
	<?php if ( ! pks_oi_sidebar_active() ) : ?>
		<header class="pks-oi-header">
			<h2><?php echo esc_html( (string) ( $project['event_title'] ?? sprintf( __( 'Invitation project #%d', 'prikogstreg-online-invitations' ), $project_id ) ) ); ?></h2>
		</header>
	<?php endif; ?>
	<?php if ( $is_support ) : ?>
		<p class="pks-oi-support-banner" role="status"><?php esc_html_e( 'Support view — you are viewing this project as shop staff.', 'prikogstreg-online-invitations' ); ?></p>
	<?php endif; ?>

	<?php pks_oi_render_notices( $notices ); ?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<?php
	pks_oi_section_open(
		'pks-oi-overview-title',
		__( 'Overview', 'prikogstreg-online-invitations' ),
		__( 'Summary information for this invitation project.', 'prikogstreg-online-invitations' )
	);
	?>

	<dl class="pks-oi-meta-grid">
		<div class="pks-oi-meta-grid__item">
			<dt><?php esc_html_e( 'Project status', 'prikogstreg-online-invitations' ); ?></dt>
			<dd><?php pks_oi_project_status_badge( (string) ( $project['status'] ?? '' ) ); ?></dd>
		</div>
		<div class="pks-oi-meta-grid__item">
			<dt><?php esc_html_e( 'Publication', 'prikogstreg-online-invitations' ); ?></dt>
			<dd><?php pks_oi_publication_badge( (string) ( $project['publication_status'] ?? '' ) ); ?></dd>
		</div>
		<div class="pks-oi-meta-grid__item">
			<dt><?php esc_html_e( 'Expires', 'prikogstreg-online-invitations' ); ?></dt>
			<dd><?php echo esc_html( $expires_label ); ?></dd>
		</div>
		<?php if ( '' !== $order_url ) : ?>
			<div class="pks-oi-meta-grid__item">
				<dt><?php esc_html_e( 'Order', 'prikogstreg-online-invitations' ); ?></dt>
				<dd><a href="<?php echo esc_url( $order_url ); ?>"><?php printf( esc_html__( 'View order #%d', 'prikogstreg-online-invitations' ), (int) ( $project['order_id'] ?? 0 ) ); ?></a></dd>
			</div>
		<?php endif; ?>
	</dl>

	<?php pks_oi_section_close(); ?>
<?php pks_oi_project_close(); ?>
