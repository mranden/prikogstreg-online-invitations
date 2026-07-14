<?php
/**
 * Public RSVP form partial.
 *
 * @package PrikOgStreg\OnlineInvitations
 *
 * @var array<string, mixed> $rsvp_form
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form = $rsvp_form;
$is_generic = 'generic' === (string) ( $form['link_type'] ?? '' );
?>
<div
	class="pks-oi-rsvp"
	data-pks-oi-rsvp
	data-rest-url="<?php echo esc_attr( (string) ( $form['rest_url'] ?? '' ) ); ?>"
	data-rest-nonce="<?php echo esc_attr( (string) ( $form['rest_nonce'] ?? '' ) ); ?>"
	data-link-type="<?php echo esc_attr( (string) ( $form['link_type'] ?? '' ) ); ?>"
>
	<?php if ( ! empty( $form['deadline_label'] ) ) : ?>
		<p class="pks-oi-rsvp__deadline">
			<?php
			printf(
				esc_html__( 'Please respond by %s.', 'prikogstreg-online-invitations' ),
				esc_html( (string) $form['deadline_label'] )
			);
			?>
		</p>
	<?php endif; ?>

	<?php if ( empty( $form['is_open'] ) ) : ?>
		<div class="pks-oi-rsvp__closed" role="status">
			<p><?php esc_html_e( 'The RSVP deadline has passed. Your recorded response is shown below.', 'prikogstreg-online-invitations' ); ?></p>
			<?php if ( ! empty( $form['has_prior_response'] ) ) : ?>
				<dl class="pks-oi-rsvp__summary">
					<dt><?php esc_html_e( 'Response', 'prikogstreg-online-invitations' ); ?></dt>
					<dd>
						<?php
						echo esc_html(
							true === $form['attending']
								? __( 'Attending', 'prikogstreg-online-invitations' )
								: ( false === $form['attending'] ? __( 'Not attending', 'prikogstreg-online-invitations' ) : __( 'Pending', 'prikogstreg-online-invitations' ) )
						);
						?>
					</dd>
					<?php if ( ! empty( $form['attendee_count_enabled'] ) && null !== ( $form['attendee_count'] ?? null ) ) : ?>
						<dt><?php esc_html_e( 'Attendees', 'prikogstreg-online-invitations' ); ?></dt>
						<dd><?php echo esc_html( (string) $form['attendee_count'] ); ?></dd>
					<?php endif; ?>
				</dl>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<form class="pks-oi-rsvp__form" data-pks-oi-rsvp-form novalidate>
			<?php if ( $is_generic ) : ?>
				<p>
					<label for="pks-oi-rsvp-name"><?php esc_html_e( 'Your name', 'prikogstreg-online-invitations' ); ?> <span aria-hidden="true">*</span></label><br />
					<input type="text" id="pks-oi-rsvp-name" name="display_name" required autocomplete="name" />
				</p>
				<p>
					<label for="pks-oi-rsvp-email"><?php esc_html_e( 'E-mail (optional)', 'prikogstreg-online-invitations' ); ?></label><br />
					<input type="email" id="pks-oi-rsvp-email" name="email" autocomplete="email" />
				</p>
			<?php endif; ?>

			<fieldset class="pks-oi-rsvp__attending">
				<legend><?php esc_html_e( 'Will you attend?', 'prikogstreg-online-invitations' ); ?></legend>
				<label>
					<input type="radio" name="attending" value="yes" <?php checked( true, $form['attending'] ?? null ); ?> required />
					<?php esc_html_e( 'Yes', 'prikogstreg-online-invitations' ); ?>
				</label>
				<label>
					<input type="radio" name="attending" value="no" <?php checked( false, $form['attending'] ?? null, false ); ?> />
					<?php esc_html_e( 'No', 'prikogstreg-online-invitations' ); ?>
				</label>
			</fieldset>

			<?php if ( ! empty( $form['attendee_count_enabled'] ) ) : ?>
				<p class="pks-oi-rsvp__attendee-count" data-pks-oi-attendee-wrap hidden>
					<label for="pks-oi-rsvp-count"><?php esc_html_e( 'Number attending', 'prikogstreg-online-invitations' ); ?></label><br />
					<input
						type="number"
						id="pks-oi-rsvp-count"
						name="attendee_count"
						min="1"
						max="50"
						value="<?php echo esc_attr( (string) ( $form['attendee_count'] ?? '1' ) ); ?>"
					/>
				</p>
			<?php endif; ?>

			<?php if ( ! empty( $form['comment_enabled'] ) ) : ?>
				<p>
					<label for="pks-oi-rsvp-comment"><?php esc_html_e( 'Comment (optional)', 'prikogstreg-online-invitations' ); ?></label><br />
					<textarea id="pks-oi-rsvp-comment" name="rsvp_comment" rows="3"><?php echo esc_textarea( (string) ( $form['rsvp_comment'] ?? '' ) ); ?></textarea>
				</p>
			<?php endif; ?>

			<?php if ( ! empty( $form['dietary_notes_enabled'] ) ) : ?>
				<p>
					<label for="pks-oi-rsvp-dietary"><?php esc_html_e( 'Dietary notes (optional)', 'prikogstreg-online-invitations' ); ?></label><br />
					<textarea id="pks-oi-rsvp-dietary" name="dietary_notes" rows="2"><?php echo esc_textarea( (string) ( $form['dietary_notes'] ?? '' ) ); ?></textarea>
				</p>
			<?php endif; ?>

			<p class="pks-oi-rsvp__actions">
				<button type="submit" class="pks-oi-rsvp__submit"><?php esc_html_e( 'Send response', 'prikogstreg-online-invitations' ); ?></button>
			</p>
			<p class="pks-oi-rsvp__status" data-pks-oi-rsvp-status role="status" aria-live="polite"></p>
			<p class="pks-oi-rsvp__personal-link" data-pks-oi-personal-link hidden></p>
		</form>
	<?php endif; ?>
</div>
