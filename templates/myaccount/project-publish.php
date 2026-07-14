<?php
/**
 * Publish, unpublish, and demo-to-self actions.
 *
 * @package PrikOgStreg\OnlineInvitations
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/_helpers.php';

use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;

$published_flag   = isset( $_GET['pks_oi_published'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$unpublished_flag = isset( $_GET['pks_oi_unpublished'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$demo_flag        = isset( $_GET['pks_oi_demo'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$is_published     = PublicationStatus::PUBLISHED === (string) ( $project['publication_status'] ?? '' );
?>
<div class="pks-oi pks-oi-myaccount pks-oi-project">
	<?php pks_oi_render_notices( $notices ); ?>
	<?php
	if ( $published_flag ) {
		pks_oi_render_notices( [ [ 'type' => 'success', 'message' => __( 'Your invitation is published.', 'prikogstreg-online-invitations' ) ] ] );
	}
	if ( $unpublished_flag ) {
		pks_oi_render_notices( [ [ 'type' => 'success', 'message' => __( 'Your invitation is unpublished.', 'prikogstreg-online-invitations' ) ] ] );
	}
	if ( $demo_flag ) {
		pks_oi_render_notices( [ [ 'type' => 'success', 'message' => __( 'Demo invitation queued for your email.', 'prikogstreg-online-invitations' ) ] ] );
	}
	?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<section class="pks-oi-publish" aria-labelledby="pks-oi-publish-title">
		<h3 id="pks-oi-publish-title"><?php esc_html_e( 'Publish', 'prikogstreg-online-invitations' ); ?></h3>

		<p>
			<?php
			if ( $is_published ) {
				esc_html_e( 'Status: published', 'prikogstreg-online-invitations' );
			} else {
				esc_html_e( 'Status: unpublished', 'prikogstreg-online-invitations' );
			}
			?>
		</p>

		<?php if ( ! $can_edit ) : ?>
			<p><?php esc_html_e( 'Publishing is not available for this project.', 'prikogstreg-online-invitations' ); ?></p>
		<?php else : ?>
			<?php if ( ! $can_publish && ! $is_published ) : ?>
				<p><?php esc_html_e( 'Complete your design and event details before publishing.', 'prikogstreg-online-invitations' ); ?></p>
			<?php endif; ?>

			<form method="post" action="" class="pks-oi-form pks-oi-publish-form">
				<?php wp_nonce_field( \PrikOgStreg\OnlineInvitations\MyAccount\ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>

				<?php if ( ! $is_published ) : ?>
					<input type="hidden" name="pks_oi_action" value="publish" />
					<p><button type="submit" class="button button-primary" <?php disabled( ! $can_publish ); ?>><?php esc_html_e( 'Publish invitation', 'prikogstreg-online-invitations' ); ?></button></p>
				<?php else : ?>
					<input type="hidden" name="pks_oi_action" value="unpublish" />
					<p><button type="submit" class="button"><?php esc_html_e( 'Unpublish', 'prikogstreg-online-invitations' ); ?></button></p>
				<?php endif; ?>
			</form>

			<form method="post" action="" class="pks-oi-form pks-oi-demo-form">
				<?php wp_nonce_field( \PrikOgStreg\OnlineInvitations\MyAccount\ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
				<input type="hidden" name="pks_oi_action" value="send_demo" />
				<p>
					<button type="submit" class="button"><?php esc_html_e( 'Send demo to myself', 'prikogstreg-online-invitations' ); ?></button>
					<span class="description"><?php esc_html_e( 'Sends a private preview link. No guest RSVP or open tracking.', 'prikogstreg-online-invitations' ); ?></span>
				</p>
			</form>
		<?php endif; ?>
	</section>
</div>
