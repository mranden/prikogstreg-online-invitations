<?php
/**
 * Responsive published poster viewport with optional multi-page navigation.
 *
 * @package PrikOgStreg\OnlineInvitations
 *
 * @var \PrikOgStreg\OnlineInvitations\Public\EnvelopeViewModel $view
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$poster_width  = max( 1, (int) ( $view->poster['width'] ?? 510 ) );
$poster_height = max( 1, (int) ( $view->poster['height'] ?? 680 ) );
$page_count    = max( 1, $view->page_count );
$has_nav       = $view->has_multiple_pages();
?>
<div
	class="pks-oi-poster-viewport"
	data-poster-width="<?php echo esc_attr( (string) $poster_width ); ?>"
	data-poster-height="<?php echo esc_attr( (string) $poster_height ); ?>"
	style="--pks-oi-poster-width: <?php echo esc_attr( (string) $poster_width ); ?>; --pks-oi-poster-height: <?php echo esc_attr( (string) $poster_height ); ?>;"
>
	<?php if ( $has_nav ) : ?>
		<nav class="pks-oi-poster-pages" aria-label="<?php esc_attr_e( 'Invitation pages', 'prikogstreg-online-invitations' ); ?>">
			<button
				type="button"
				class="pks-oi-poster-pages__prev"
				data-pks-oi-poster-prev
				aria-label="<?php esc_attr_e( 'Previous page', 'prikogstreg-online-invitations' ); ?>"
				disabled
			>
				<?php esc_html_e( 'Previous page', 'prikogstreg-online-invitations' ); ?>
			</button>
			<p class="pks-oi-poster-pages__status" data-pks-oi-poster-status aria-live="polite">
				<?php
				printf(
					/* translators: 1: current page number, 2: total pages */
					esc_html__( 'Page %1$d of %2$d', 'prikogstreg-online-invitations' ),
					1,
					$page_count
				);
				?>
			</p>
			<button
				type="button"
				class="pks-oi-poster-pages__next"
				data-pks-oi-poster-next
				aria-label="<?php esc_attr_e( 'Next page', 'prikogstreg-online-invitations' ); ?>"
				<?php echo $page_count < 2 ? 'disabled' : ''; ?>
			>
				<?php esc_html_e( 'Next page', 'prikogstreg-online-invitations' ); ?>
			</button>
		</nav>
	<?php endif; ?>

	<div class="pks-oi-poster-viewport__frame">
		<div class="pks-oi-poster-viewport__canvas bpp-public-invitation" data-pks-oi-poster-canvas>
			<?php foreach ( $view->invitation_pages as $page_index => $page ) : ?>
				<?php
				$page_number = (int) ( $page['index'] ?? ( $page_index + 1 ) );
				$is_active   = 0 === $page_index;
				?>
				<div
					class="pks-oi-poster-page"
					data-pks-oi-poster-page="<?php echo esc_attr( (string) $page_number ); ?>"
					<?php echo $is_active ? '' : 'hidden'; ?>
					aria-hidden="<?php echo $is_active ? 'false' : 'true'; ?>"
				>
					<?php echo (string) ( $page['html'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized published snapshot ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
