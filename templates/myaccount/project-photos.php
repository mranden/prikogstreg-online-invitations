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

$project_id = (int) ( $project_id ?? 0 );
?>
<div class="pks-oi pks-oi-myaccount pks-oi-project">
	<?php pks_oi_render_notices( $notices ); ?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<section aria-labelledby="pks-oi-photos-title">
		<h3 id="pks-oi-photos-title"><?php esc_html_e( 'Guest photos', 'prikogstreg-online-invitations' ); ?></h3>
		<p><?php esc_html_e( 'Review pending uploads, approve or reject them, and download approved files. Approved photos are not published to a public gallery.', 'prikogstreg-online-invitations' ); ?></p>

		<nav class="pks-oi-photos__filters" aria-label="<?php esc_attr_e( 'Photo status filter', 'prikogstreg-online-invitations' ); ?>">
			<?php
			$filters = [
				PhotoModerationStatus::PENDING  => __( 'Pending', 'prikogstreg-online-invitations' ),
				PhotoModerationStatus::APPROVED => __( 'Approved', 'prikogstreg-online-invitations' ),
				PhotoModerationStatus::REJECTED => __( 'Rejected', 'prikogstreg-online-invitations' ),
				'all'                           => __( 'All', 'prikogstreg-online-invitations' ),
			];
			foreach ( $filters as $key => $label ) :
				$url = add_query_arg( 'pks_oi_photo_status', $key, Endpoints::project_url( $project_id, 'photos' ) );
				?>
				<a href="<?php echo esc_url( $url ); ?>" class="<?php echo ( $status_filter ?? '' ) === $key ? 'is-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>

		<?php if ( empty( $photos ) ) : ?>
			<p><?php esc_html_e( 'No photos in this view yet.', 'prikogstreg-online-invitations' ); ?></p>
		<?php else : ?>
			<table class="shop_table shop_table_responsive pks-oi-photos__table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Guest', 'prikogstreg-online-invitations' ); ?></th>
						<th><?php esc_html_e( 'File', 'prikogstreg-online-invitations' ); ?></th>
						<th><?php esc_html_e( 'Size', 'prikogstreg-online-invitations' ); ?></th>
						<th><?php esc_html_e( 'Status', 'prikogstreg-online-invitations' ); ?></th>
						<th><?php esc_html_e( 'Uploaded', 'prikogstreg-online-invitations' ); ?></th>
						<?php if ( $can_edit ) : ?>
							<th><?php esc_html_e( 'Actions', 'prikogstreg-online-invitations' ); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $photos as $photo ) : ?>
						<tr>
							<td><?php echo esc_html( (string) ( $photo['guest_name'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $photo['original_filename'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( size_format( (int) ( $photo['byte_size'] ?? 0 ), 1 ) ); ?></td>
							<td><?php echo esc_html( (string) ( $photo['moderation_status'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $photo['created_at_utc'] ?? '' ) ); ?></td>
							<?php if ( $can_edit ) : ?>
								<td>
									<?php
									$download_url = wp_nonce_url(
										add_query_arg(
											[
												'pks_oi_photo_download' => (int) ( $photo['photo_id'] ?? 0 ),
												'pks_oi_project'        => $project_id,
											],
											Endpoints::project_url( $project_id, 'photos' )
										),
										'pks_oi_photo_download_' . (int) ( $photo['photo_id'] ?? 0 )
									);
									?>
									<a href="<?php echo esc_url( $download_url ); ?>"><?php esc_html_e( 'Download', 'prikogstreg-online-invitations' ); ?></a>
									<?php if ( PhotoModerationStatus::PENDING === (string) ( $photo['moderation_status'] ?? '' ) ) : ?>
										<form method="post" class="pks-oi-inline-form">
											<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
											<input type="hidden" name="pks_oi_action" value="approve_photo" />
											<input type="hidden" name="photo_id" value="<?php echo esc_attr( (string) ( $photo['photo_id'] ?? 0 ) ); ?>" />
											<button type="submit" class="button"><?php esc_html_e( 'Approve', 'prikogstreg-online-invitations' ); ?></button>
										</form>
										<form method="post" class="pks-oi-inline-form">
											<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
											<input type="hidden" name="pks_oi_action" value="reject_photo" />
											<input type="hidden" name="photo_id" value="<?php echo esc_attr( (string) ( $photo['photo_id'] ?? 0 ) ); ?>" />
											<button type="submit" class="button"><?php esc_html_e( 'Reject', 'prikogstreg-online-invitations' ); ?></button>
										</form>
									<?php endif; ?>
									<form method="post" class="pks-oi-inline-form">
										<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
										<input type="hidden" name="pks_oi_action" value="delete_photo" />
										<input type="hidden" name="photo_id" value="<?php echo esc_attr( (string) ( $photo['photo_id'] ?? 0 ) ); ?>" />
										<button type="submit" class="button"><?php esc_html_e( 'Delete', 'prikogstreg-online-invitations' ); ?></button>
									</form>
								</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</section>
</div>
