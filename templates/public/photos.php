<?php
/**
 * Public guest photo upload section.
 *
 * @package PrikOgStreg\OnlineInvitations
 *
 * @var array<string, mixed> $photos
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$intent_url = (string) ( $photos['intent_url'] ?? '' );
$upload_url = (string) ( $photos['upload_url'] ?? '' );
$rest_nonce = (string) ( $photos['rest_nonce'] ?? '' );
$requires_name = ! empty( $photos['requires_name'] );
$max_files = (int) ( $photos['max_files'] ?? 10 );
?>
<div
	class="pks-oi-photos"
	data-pks-oi-photos
	data-intent-url="<?php echo esc_attr( $intent_url ); ?>"
	data-upload-url="<?php echo esc_attr( $upload_url ); ?>"
	data-rest-nonce="<?php echo esc_attr( $rest_nonce ); ?>"
	data-requires-name="<?php echo $requires_name ? '1' : '0'; ?>"
	data-max-files="<?php echo esc_attr( (string) $max_files ); ?>"
>
	<p><?php esc_html_e( 'Share photos from the event. Uploads are private until the organiser approves them.', 'prikogstreg-online-invitations' ); ?></p>

	<?php if ( $requires_name ) : ?>
		<p>
			<label for="pks-oi-photos-name"><?php esc_html_e( 'Your name', 'prikogstreg-online-invitations' ); ?></label><br />
			<input type="text" id="pks-oi-photos-name" data-pks-oi-photos-name />
		</p>
	<?php endif; ?>

	<p>
		<label for="pks-oi-photos-input"><?php esc_html_e( 'Choose photos (JPEG, PNG, or WebP)', 'prikogstreg-online-invitations' ); ?></label><br />
		<input
			type="file"
			id="pks-oi-photos-input"
			data-pks-oi-photos-input
			accept="image/jpeg,image/png,image/webp"
			multiple
		/>
	</p>
	<p>
		<button type="button" class="button" data-pks-oi-photos-upload><?php esc_html_e( 'Upload photos', 'prikogstreg-online-invitations' ); ?></button>
	</p>
	<p class="pks-oi-photos__status pks-oi-status" data-pks-oi-photos-status role="status" aria-live="polite" hidden></p>
</div>
