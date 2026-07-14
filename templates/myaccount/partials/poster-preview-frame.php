<?php
/**
 * Responsive poster viewport for My Account design/preview sections.
 *
 * @package PrikOgStreg\OnlineInvitations
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$poster_width  = max( 1, (int) ( $poster_width ?? 510 ) );
$poster_height = max( 1, (int) ( $poster_height ?? 680 ) );
$preview_html  = (string) ( $preview_html ?? '' );
?>
<div class="pks-oi-preview__device">
	<div
		class="pks-oi-poster-viewport"
		data-poster-width="<?php echo esc_attr( (string) $poster_width ); ?>"
		data-poster-height="<?php echo esc_attr( (string) $poster_height ); ?>"
		style="--pks-oi-poster-width: <?php echo esc_attr( (string) $poster_width ); ?>; --pks-oi-poster-height: <?php echo esc_attr( (string) $poster_height ); ?>;"
	>
		<div class="pks-oi-poster-viewport__frame">
			<div class="pks-oi-poster-viewport__canvas bpp-public-invitation" data-pks-oi-poster-canvas>
				<?php echo $preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
	</div>
</div>
