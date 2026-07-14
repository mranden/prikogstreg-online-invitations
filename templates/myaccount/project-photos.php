<?php
/**
 * Owner photo moderation.
 *
 * @package PrikOgStreg\OnlineInvitations
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/_helpers.php';

use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoModerationStatus;
use PrikOgStreg\OnlineInvitations\MyAccount\Endpoints;
use PrikOgStreg\OnlineInvitations\MyAccount\ProjectController;
use PrikOgStreg\OnlineInvitations\MyAccount\ProjectSections;

$project_id = (int) ( $project_id ?? 0 );
$photos_url = Endpoints::project_url( $project_id, ProjectSections::PHOTOS );
$filters    = [
	PhotoModerationStatus::PENDING  => __( 'Pending', 'prikogstreg-online-invitations' ),
	PhotoModerationStatus::APPROVED => __( 'Approved', 'prikogstreg-online-invitations' ),
	PhotoModerationStatus::REJECTED => __( 'Rejected', 'prikogstreg-online-invitations' ),
	'all'                           => __( 'All', 'prikogstreg-online-invitations' ),
];
$current_filter = (string) ( $status_filter ?? 'all' );
if ( '' === $current_filter ) {
	$current_filter = 'all';
}
?>
<?php pks_oi_project_open(); ?>
	<?php pks_oi_render_notices( $notices ); ?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<?php
	pks_oi_section_open(
		'pks-oi-photos-title',
		__( 'Guest photos', 'prikogstreg-online-invitations' ),
		__( 'Optional — review uploads from guests. Approved photos are not shown in a public gallery.', 'prikogstreg-online-invitations' )
	);
	?>

	<?php pks_oi_render_filter_pills( $photos_url, $filters, $current_filter, 'pks_oi_photo_status' ); ?>

	<?php if ( empty( $photos ) ) : ?>
		<?php
		pks_oi_render_empty_state(
			__( 'No photos in this view', 'prikogstreg-online-invitations' ),
			__( 'Guest photo uploads will appear here for moderation.', 'prikogstreg-online-invitations' )
		);
		?>
	<?php else : ?>
		<div class="pks-oi-photo-grid">
			<?php foreach ( $photos as $photo ) : ?>
				<article class="pks-oi-photo-card">
					<div class="pks-oi-photo-card__thumb"><?php echo esc_html( (string) ( $photo['original_filename'] ?? __( 'Photo', 'prikogstreg-online-invitations' ) ) ); ?></div>
					<div class="pks-oi-photo-card__body">
						<p><strong><?php echo esc_html( (string) ( $photo['guest_name'] ?? '' ) ); ?></strong></p>
						<p class="pks-oi-field__hint"><?php echo esc_html( size_format( (int) ( $photo['byte_size'] ?? 0 ), 1 ) ); ?> · <?php echo esc_html( pks_oi_format_datetime_display( (string) ( $photo['created_at_utc'] ?? '' ) ) ); ?></p>
						<?php
						$mod_status = (string) ( $photo['moderation_status'] ?? '' );
						pks_oi_render_badge(
							ucfirst( $mod_status ),
							PhotoModerationStatus::APPROVED === $mod_status ? 'success' : ( PhotoModerationStatus::PENDING === $mod_status ? 'warning' : 'danger' )
						);
						?>
						<?php if ( $can_edit ) : ?>
							<div class="pks-oi-table__actions">
								<?php
								$download_url = wp_nonce_url(
									add_query_arg(
										[
											'pks_oi_photo_download' => (int) ( $photo['photo_id'] ?? 0 ),
											'pks_oi_project'        => $project_id,
										],
										$photos_url
									),
									'pks_oi_photo_download_' . (int) ( $photo['photo_id'] ?? 0 )
								);
								?>
								<a class="button" href="<?php echo esc_url( $download_url ); ?>"><?php esc_html_e( 'Download', 'prikogstreg-online-invitations' ); ?></a>
								<?php if ( PhotoModerationStatus::PENDING === $mod_status ) : ?>
									<form method="post" class="pks-oi-inline-form">
										<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
										<input type="hidden" name="pks_oi_action" value="approve_photo" />
										<input type="hidden" name="photo_id" value="<?php echo esc_attr( (string) ( $photo['photo_id'] ?? 0 ) ); ?>" />
										<button type="submit" class="button button-primary"><?php esc_html_e( 'Approve', 'prikogstreg-online-invitations' ); ?></button>
									</form>
									<form method="post" class="pks-oi-inline-form">
										<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
										<input type="hidden" name="pks_oi_action" value="reject_photo" />
										<input type="hidden" name="photo_id" value="<?php echo esc_attr( (string) ( $photo['photo_id'] ?? 0 ) ); ?>" />
										<button type="submit" class="button"><?php esc_html_e( 'Reject', 'prikogstreg-online-invitations' ); ?></button>
									</form>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php pks_oi_section_close(); ?>
<?php pks_oi_project_close(); ?>
