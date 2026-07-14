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

$published_flag = isset( $_GET['pks_oi_published'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$unpublished_flag = isset( $_GET['pks_oi_unpublished'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$demo_flag = isset( $_GET['pks_oi_demo'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$is_published = PublicationStatus::PUBLISHED === (string) ( $project['publication_status'] ?? '' );
?>
<?php pks_oi_project_open(); ?>
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

	<?php
	pks_oi_section_open(
		'pks-oi-publish-title',
		__( 'Publish', 'prikogstreg-online-invitations' ),
		__( 'Make your invitation live when design and event details are ready.', 'prikogstreg-online-invitations' )
	);
	?>

	<?php if ( $is_published ) : ?>
		<?php pks_oi_render_card_open( __( 'Your invitation is live', 'prikogstreg-online-invitations' ), 'pks-oi-card--success' ); ?>
			<p><?php esc_html_e( 'Guests can view and respond to your published invitation.', 'prikogstreg-online-invitations' ); ?></p>
			<?php pks_oi_publication_badge( 'published' ); ?>
		<?php pks_oi_render_card_close(); ?>
	<?php else : ?>
		<?php pks_oi_render_card_open( __( 'Ready to publish?', 'prikogstreg-online-invitations' ) ); ?>
			<?php pks_oi_render_checklist_cards( $checklist ); ?>
		<?php pks_oi_render_card_close(); ?>
	<?php endif; ?>

	<?php if ( ! $can_edit ) : ?>
		<?php
		pks_oi_render_empty_state(
			__( 'Publishing unavailable', 'prikogstreg-online-invitations' ),
			__( 'Publishing is not available for this project.', 'prikogstreg-online-invitations' )
		);
		?>
	<?php else : ?>
		<div class="pks-oi-hero-cta">
			<?php if ( ! $is_published ) : ?>
				<h4 class="pks-oi-hero-cta__title"><?php esc_html_e( 'Publish invitation', 'prikogstreg-online-invitations' ); ?></h4>
				<?php if ( ! $can_publish ) : ?>
					<p class="pks-oi-hero-cta__text"><?php esc_html_e( 'Complete your design and event details before publishing.', 'prikogstreg-online-invitations' ); ?></p>
				<?php endif; ?>
				<form method="post" action="" class="pks-oi-form pks-oi-publish-form">
					<?php wp_nonce_field( \PrikOgStreg\OnlineInvitations\MyAccount\ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
					<input type="hidden" name="pks_oi_action" value="publish" />
					<button type="submit" class="button button-primary" <?php disabled( ! $can_publish ); ?>><?php esc_html_e( 'Publish invitation', 'prikogstreg-online-invitations' ); ?></button>
				</form>
			<?php else : ?>
				<form method="post" action="" class="pks-oi-form pks-oi-publish-form">
					<?php wp_nonce_field( \PrikOgStreg\OnlineInvitations\MyAccount\ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
					<input type="hidden" name="pks_oi_action" value="unpublish" />
					<button type="submit" class="button"><?php esc_html_e( 'Unpublish', 'prikogstreg-online-invitations' ); ?></button>
				</form>
			<?php endif; ?>
		</div>

		<?php pks_oi_render_card_open( __( 'Send demo to yourself', 'prikogstreg-online-invitations' ) ); ?>
			<form method="post" action="" class="pks-oi-form pks-oi-demo-form">
				<?php wp_nonce_field( \PrikOgStreg\OnlineInvitations\MyAccount\ProjectController::NONCE_ACTION, 'pks_oi_nonce' ); ?>
				<input type="hidden" name="pks_oi_action" value="send_demo" />
				<p class="pks-oi-field__hint"><?php esc_html_e( 'Sends a private preview link to your email. No guest RSVP or open tracking.', 'prikogstreg-online-invitations' ); ?></p>
				<button type="submit" class="button"><?php esc_html_e( 'Send demo to myself', 'prikogstreg-online-invitations' ); ?></button>
			</form>
		<?php pks_oi_render_card_close(); ?>
	<?php endif; ?>

	<?php pks_oi_section_close(); ?>
<?php pks_oi_project_close(); ?>
