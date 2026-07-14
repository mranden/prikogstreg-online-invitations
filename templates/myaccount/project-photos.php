<?php
/**
 * Owner photo settings, share tools, and moderation.
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

$project_id     = (int) ( $project_id ?? 0 );
$photos_url     = Endpoints::project_url( $project_id, ProjectSections::PHOTOS );
$summary        = is_array( $photo_summary ?? null ) ? $photo_summary : [];
$share_url      = (string) ( $summary['share_url'] ?? '' );
$preview_url    = (string) ( $preview_url ?? $share_url );
$qr_url         = (string) ( $qr_url ?? '' );
$guests         = is_array( $guests ?? null ) ? $guests : [];
$filters        = [
	PhotoModerationStatus::PENDING  => __( 'Pending', 'prikogstreg-online-invitations' ),
	PhotoModerationStatus::APPROVED => __( 'Approved', 'prikogstreg-online-invitations' ),
	PhotoModerationStatus::REJECTED => __( 'Rejected', 'prikogstreg-online-invitations' ),
	'all'                           => __( 'All', 'prikogstreg-online-invitations' ),
];
$current_filter = (string) ( $status_filter ?? PhotoModerationStatus::PENDING );
$saved          = isset( $_GET['pks_oi_saved'] ) && '1' === (string) $_GET['pks_oi_saved']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$save_error     = isset( $_GET['pks_oi_error'] ) ? sanitize_key( (string) wp_unslash( $_GET['pks_oi_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$display_code   = (string) ( $summary['display_access_code'] ?? '' );
$share_configured = ! empty( $summary['owner_share_configured'] );
$wall_url       = (string) ( $summary['wall_url'] ?? '' );
?>
<?php pks_oi_project_open(); ?>
	<?php pks_oi_render_notices( $notices ); ?>
	<?php if ( $saved ) : ?>
		<?php pks_oi_render_notices( [ [ 'type' => 'success', 'message' => __( 'Photo settings saved.', 'prikogstreg-online-invitations' ) ] ] ); ?>
	<?php endif; ?>
	<?php if ( '' !== $save_error ) : ?>
		<?php
		$error_messages = [
			'code_too_short'  => __( 'The photo code must be at least 4 characters.', 'prikogstreg-online-invitations' ),
			'code_mismatch'   => __( 'The photo codes did not match.', 'prikogstreg-online-invitations' ),
		];
		pks_oi_render_notices(
			[
				[
					'type'    => 'error',
					'message' => $error_messages[ $save_error ] ?? __( 'Photo settings could not be saved.', 'prikogstreg-online-invitations' ),
				],
			]
		);
		?>
	<?php endif; ?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<?php
	pks_oi_section_open(
		'pks-oi-photos-title',
		__( 'Guest photos', 'prikogstreg-online-invitations' ),
		__( 'Configure photo sharing, share the link with guests, and review uploads.', 'prikogstreg-online-invitations' )
	);
	?>

	<?php if ( $can_edit ) : ?>
		<?php pks_oi_render_card_open( __( 'Photo sharing settings', 'prikogstreg-online-invitations' ) ); ?>
			<form method="post" class="pks-oi-form">
				<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
				<input type="hidden" name="pks_oi_action" value="save_photo_settings" />
				<p>
					<label>
						<input type="checkbox" name="guest_photos_enabled" value="1" <?php checked( ! empty( $summary['enabled'] ) ); ?> />
						<?php esc_html_e( 'Enable guest photo sharing', 'prikogstreg-online-invitations' ); ?>
					</label>
				</p>
				<p>
					<label>
						<input type="checkbox" name="photo_auto_approve_enabled" value="1" <?php checked( ! empty( $summary['auto_approve_enabled'] ) ); ?> />
						<?php esc_html_e( 'Auto-approve uploads (photos appear on the wall immediately)', 'prikogstreg-online-invitations' ); ?>
					</label>
				</p>
				<p>
					<label>
						<input type="checkbox" name="photo_gallery_public_enabled" value="1" <?php checked( ! empty( $summary['gallery_public_enabled'] ) ); ?> />
						<?php esc_html_e( 'Enable public photo wall', 'prikogstreg-online-invitations' ); ?>
					</label>
				</p>
				<?php
				pks_oi_render_field(
					[
						'label'       => __( 'Close uploads at', 'prikogstreg-online-invitations' ),
						'name'        => 'photo_upload_closes_at_utc',
						'type'        => 'datetime-local',
						'value'       => (string) ( $summary['upload_closes_at_utc'] ?? '' ),
						'description' => __( 'Optional. Leave empty to keep uploads open until project expiry.', 'prikogstreg-online-invitations' ),
					]
				);
				?>
				<p><strong><?php esc_html_e( 'Photo code (fotokode)', 'prikogstreg-online-invitations' ); ?></strong></p>
				<?php if ( '' !== $display_code ) : ?>
					<p class="pks-oi-photo-code-display">
						<strong><?php esc_html_e( 'Your photo code', 'prikogstreg-online-invitations' ); ?>:</strong>
						<code class="pks-oi-photo-code-display__value"><?php echo esc_html( $display_code ); ?></code>
					</p>
					<p class="pks-oi-field__hint"><?php esc_html_e( 'Share this code with guests separately from the link. Enter a new code below to change it.', 'prikogstreg-online-invitations' ); ?></p>
				<?php else : ?>
					<p class="pks-oi-field__hint"><?php esc_html_e( 'Enter a photo code guests must use to upload. It will be shown here after you save.', 'prikogstreg-online-invitations' ); ?></p>
				<?php endif; ?>
				<?php
				pks_oi_render_field(
					[
						'label' => __( 'New photo code', 'prikogstreg-online-invitations' ),
						'name'  => 'photo_access_code',
						'type'  => 'password',
					]
				);
				pks_oi_render_field(
					[
						'label' => __( 'Confirm photo code', 'prikogstreg-online-invitations' ),
						'name'  => 'photo_access_code_confirm',
						'type'  => 'password',
					]
				);
				?>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'prikogstreg-online-invitations' ); ?></button>
			</form>
		<?php pks_oi_render_card_close(); ?>

		<?php if ( $share_configured && '' !== $share_url ) : ?>
			<?php pks_oi_render_card_open( __( 'Share tools', 'prikogstreg-online-invitations' ) ); ?>
				<div class="pks-oi-photo-share-tools" data-pks-oi-photo-share-tools data-share-url="<?php echo esc_attr( $share_url ); ?>" data-wall-url="<?php echo esc_attr( $wall_url ); ?>">
					<p>
						<label class="pks-oi-field__label" for="pks-oi-share-upload-url"><?php esc_html_e( 'Upload link (guests enter photo code)', 'prikogstreg-online-invitations' ); ?></label><br />
						<input type="text" readonly class="widefat" id="pks-oi-share-upload-url" value="<?php echo esc_attr( $share_url ); ?>" data-pks-oi-share-url-input />
					</p>
					<?php if ( '' !== $wall_url ) : ?>
						<p>
							<label class="pks-oi-field__label" for="pks-oi-share-wall-url"><?php esc_html_e( 'Photo wall link (view all photos)', 'prikogstreg-online-invitations' ); ?></label><br />
							<input type="text" readonly class="widefat" id="pks-oi-share-wall-url" value="<?php echo esc_attr( $wall_url ); ?>" data-pks-oi-wall-url-input />
						</p>
					<?php endif; ?>
					<?php if ( '' !== (string) ( $qr_svg ?? '' ) ) : ?>
						<div class="pks-oi-photo-share-tools__qr" aria-label="<?php esc_attr_e( 'QR code for photo sharing link', 'prikogstreg-online-invitations' ); ?>">
							<?php echo $qr_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted SVG from PhotoShareQrService ?>
						</div>
					<?php endif; ?>
					<p class="pks-oi-table__actions">
						<button type="button" class="button" data-pks-oi-copy-share-url><?php esc_html_e( 'Copy upload link', 'prikogstreg-online-invitations' ); ?></button>
						<?php if ( '' !== $wall_url ) : ?>
							<button type="button" class="button" data-pks-oi-copy-wall-url><?php esc_html_e( 'Copy photo wall link', 'prikogstreg-online-invitations' ); ?></button>
							<a class="button" href="<?php echo esc_url( $wall_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open photo wall', 'prikogstreg-online-invitations' ); ?></a>
						<?php endif; ?>
						<button type="button" class="button" data-pks-oi-native-share hidden><?php esc_html_e( 'Share', 'prikogstreg-online-invitations' ); ?></button>
						<?php if ( '' !== $preview_url ) : ?>
							<a class="button" href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Preview guest page', 'prikogstreg-online-invitations' ); ?></a>
						<?php endif; ?>
						<?php if ( '' !== $qr_url ) : ?>
							<a class="button" href="<?php echo esc_url( $qr_url ); ?>"><?php esc_html_e( 'Download QR code', 'prikogstreg-online-invitations' ); ?></a>
						<?php endif; ?>
					</p>
					<p class="pks-oi-field__hint"><?php esc_html_e( 'Share the upload link and photo code with guests. Share the photo wall link for everyone to view photos.', 'prikogstreg-online-invitations' ); ?></p>
					<p class="pks-oi-status" data-pks-oi-share-tools-status role="status" aria-live="polite" hidden></p>
				</div>
				<form method="post" class="pks-oi-form">
					<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
					<input type="hidden" name="pks_oi_action" value="rotate_photo_share_link" />
					<button type="submit" class="button"><?php esc_html_e( 'Rotate share link', 'prikogstreg-online-invitations' ); ?></button>
				</form>
				<?php if ( [] !== $guests ) : ?>
					<form method="post" class="pks-oi-form">
						<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
						<input type="hidden" name="pks_oi_action" value="send_photo_share_invites" />
						<p><strong><?php esc_html_e( 'Email photo link to guests', 'prikogstreg-online-invitations' ); ?></strong></p>
						<p class="pks-oi-field__hint"><?php esc_html_e( 'The email includes the photo page link and instructions. Share the photo code separately.', 'prikogstreg-online-invitations' ); ?></p>
						<?php foreach ( $guests as $guest ) : ?>
							<?php if ( '' === trim( (string) ( $guest['email'] ?? '' ) ) ) { continue; } ?>
							<label>
								<input type="checkbox" name="guest_ids[]" value="<?php echo esc_attr( (string) ( $guest['guest_id'] ?? 0 ) ); ?>" />
								<?php echo esc_html( (string) ( $guest['display_name'] ?? '' ) ); ?>
							</label><br />
						<?php endforeach; ?>
						<button type="submit" class="button"><?php esc_html_e( 'Send email', 'prikogstreg-online-invitations' ); ?></button>
					</form>
				<?php endif; ?>
			<?php pks_oi_render_card_close(); ?>
		<?php elseif ( ! empty( $summary['enabled'] ) ) : ?>
			<p class="pks-oi-field__hint"><?php esc_html_e( 'Set a photo code and save settings to unlock the share link and QR code.', 'prikogstreg-online-invitations' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>

	<dl class="pks-oi-meta-grid">
		<div class="pks-oi-meta-grid__item"><dt><?php esc_html_e( 'Pending', 'prikogstreg-online-invitations' ); ?></dt><dd><?php echo esc_html( (string) (int) ( $summary['pending_count'] ?? 0 ) ); ?></dd></div>
		<div class="pks-oi-meta-grid__item"><dt><?php esc_html_e( 'Approved', 'prikogstreg-online-invitations' ); ?></dt><dd><?php echo esc_html( (string) (int) ( $summary['approved_count'] ?? 0 ) ); ?></dd></div>
		<div class="pks-oi-meta-grid__item"><dt><?php esc_html_e( 'Storage used', 'prikogstreg-online-invitations' ); ?></dt><dd><?php echo esc_html( size_format( (int) ( $summary['storage_bytes'] ?? 0 ), 1 ) ); ?></dd></div>
	</dl>

	<?php pks_oi_render_filter_pills( $photos_url, $filters, $current_filter, 'pks_oi_photo_status' ); ?>

	<?php if ( $can_edit && ! empty( $photos ) ) : ?>
		<form method="post" class="pks-oi-form" data-pks-oi-bulk-form>
			<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
			<input type="hidden" name="pks_oi_action" value="bulk_photo_action" />
			<p>
				<label><input type="checkbox" data-pks-oi-select-all /> <?php esc_html_e( 'Select all', 'prikogstreg-online-invitations' ); ?></label>
			</p>
			<div class="pks-oi-bulk-bar" data-pks-oi-bulk-bar>
				<select name="bulk_action">
					<option value="approve"><?php esc_html_e( 'Approve', 'prikogstreg-online-invitations' ); ?></option>
					<option value="reject"><?php esc_html_e( 'Reject', 'prikogstreg-online-invitations' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Delete', 'prikogstreg-online-invitations' ); ?></option>
				</select>
				<button type="submit" class="button"><?php esc_html_e( 'Apply to selected', 'prikogstreg-online-invitations' ); ?> (<span data-pks-oi-bulk-count>0</span>)</button>
			</div>
	<?php endif; ?>

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
					<?php if ( $can_edit ) : ?>
						<label class="pks-oi-photo-card__select">
							<input type="checkbox" name="photo_ids[]" value="<?php echo esc_attr( (string) ( $photo['photo_id'] ?? 0 ) ); ?>" data-pks-oi-row-checkbox />
						</label>
					<?php endif; ?>
					<?php
					$thumb_url = wp_nonce_url(
						add_query_arg(
							[
								'pks_oi_photo_thumb' => (int) ( $photo['photo_id'] ?? 0 ),
								'pks_oi_project'     => $project_id,
							],
							$photos_url
						),
						'pks_oi_photo_thumb_' . (int) ( $photo['photo_id'] ?? 0 )
					);
					?>
					<div class="pks-oi-photo-card__thumb">
						<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy" width="320" height="240" />
					</div>
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
								<form method="post" class="pks-oi-inline-form">
									<?php wp_nonce_field( ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
									<input type="hidden" name="pks_oi_action" value="delete_photo" />
									<input type="hidden" name="photo_id" value="<?php echo esc_attr( (string) ( $photo['photo_id'] ?? 0 ) ); ?>" />
									<button type="submit" class="button"><?php esc_html_e( 'Delete', 'prikogstreg-online-invitations' ); ?></button>
								</form>
							</div>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( $can_edit && ! empty( $photos ) ) : ?>
		</form>
	<?php endif; ?>

	<?php pks_oi_section_close(); ?>
<?php pks_oi_project_close(); ?>
