<?php
/**
 * Project design editor section.
 *
 * @package PrikOgStreg\OnlineInvitations
 *
 * @var array<string,mixed>              $project
 * @var int                              $project_id
 * @var string                           $section
 * @var bool                             $can_edit
 * @var array<string,string>             $sections
 * @var array<string,string>             $section_urls
 * @var list<array{type:string,message:string}> $notices
 * @var string                           $editor_html
 * @var string                           $editor_error
 * @var int                              $state_version
 * @var string                           $rest_save_url
 * @var string                           $rest_nonce
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/_helpers.php';
?>
<div class="pks-oi pks-oi-myaccount pks-oi-project">
	<?php pks_oi_render_notices( $notices ); ?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<section class="pks-oi-design" aria-labelledby="pks-oi-design-title">
		<h3 id="pks-oi-design-title"><?php esc_html_e( 'Design', 'prikogstreg-online-invitations' ); ?></h3>

		<?php if ( ! empty( $editor_error ) ) : ?>
			<p class="pks-oi-design__error" role="alert"><?php echo esc_html( $editor_error ); ?></p>
		<?php elseif ( '' === $editor_html ) : ?>
			<p><?php esc_html_e( 'The design editor is unavailable. Check that the PDF Builder integration is active.', 'prikogstreg-online-invitations' ); ?></p>
		<?php else : ?>
			<div
				id="pks-oi-editor"
				class="pks-oi-editor"
				data-pks-oi-rest-url="<?php echo esc_url( $rest_save_url ); ?>"
				data-pks-oi-rest-nonce="<?php echo esc_attr( $rest_nonce ); ?>"
				data-pks-oi-state-version="<?php echo esc_attr( (string) $state_version ); ?>"
				data-pks-oi-project-id="<?php echo esc_attr( (string) $project_id ); ?>"
			>
				<?php
				// Adapter-rendered editor markup (trusted builder output).
				echo $editor_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>
			<p class="pks-oi-design__save-status" id="pks-oi-save-status" aria-live="polite" hidden></p>
		<?php endif; ?>
	</section>
</div>
