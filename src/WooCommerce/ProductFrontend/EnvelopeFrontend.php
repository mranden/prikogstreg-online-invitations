<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\ProductFrontend;

use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\AttachmentValidator;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\EnvelopeDesign;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;

/**
 * Storefront envelope preview for online_invitation products.
 */
final class EnvelopeFrontend {

	public function render( object $product ): void {
		if ( ! ProductMeta::is_online_invitation( $product ) ) {
			return;
		}

		$design      = EnvelopeDesign::resolve_for_product( $product );
		$presets     = ProductMeta::envelope_presets();
		$backgrounds = ProductMeta::background_presets();
		$preset      = (string) ( $design['preset'] ?? '' );
		$background  = (string) ( $design['background_preset'] ?? '' );
		$image_url   = (string) ( $design['image_url'] ?? '' );
		$image_id    = max( 0, (int) ( $design['image_id'] ?? 0 ) );

		if ( $image_id > 0 && ! AttachmentValidator::is_valid_image_attachment( $image_id ) ) {
			$image_url = '';
			$image_id  = 0;
		}

		$preset_label     = $presets[ $preset ] ?? $preset;
		$background_label = $backgrounds[ $background ] ?? $background;

		$envelope_classes = array_filter(
			[
				'pks-oi-product-envelope-preview',
				'pks-oi-envelope',
				'' !== $preset ? 'pks-oi-envelope--' . $preset : '',
				'' !== $background ? 'pks-oi-envelope--bg-' . $background : '',
			]
		);

		printf(
			'<section class="pks-oi-product-configurator__section pks-oi-product-configurator__envelope" aria-labelledby="pks-oi-envelope-heading" data-pks-oi-section="envelope">'
		);
		printf(
			'<div class="%s" data-pks-oi-envelope-preset="%s" data-pks-oi-background-preset="%s">',
			esc_attr( implode( ' ', $envelope_classes ) ),
			esc_attr( $preset ),
			esc_attr( $background )
		);

		printf(
			'<h2 class="pks-oi-product-configurator__section-title" id="pks-oi-envelope-heading">%s</h2>',
			esc_html__( 'Envelope preview', 'prikogstreg-online-invitations' )
		);

		echo '<div class="pks-oi-product-envelope-preview__stage">';
		echo '<div class="pks-oi-product-envelope-preview__shell">';

		printf(
			'<div class="pks-oi-envelope__card" role="img" aria-label="%s">',
			esc_attr( __( 'Envelope artwork preview', 'prikogstreg-online-invitations' ) )
		);

		if ( '' !== $image_url ) {
			printf(
				'<img class="pks-oi-product-envelope-preview__card-image" src="%s" alt="%s" loading="lazy" width="240" height="160" />',
				esc_url( $image_url ),
				esc_attr( __( 'Envelope artwork', 'prikogstreg-online-invitations' ) )
			);
		} else {
			echo '<div class="pks-oi-product-envelope-preview__card-fallback" aria-hidden="true"></div>';
		}

		echo '</div>';

		echo '<div class="pks-oi-product-envelope-preview__inner" aria-hidden="true">';
		printf(
			'<span class="pks-oi-product-envelope-preview__inner-label">%s</span>',
			esc_html__( 'Your designed invitation goes inside', 'prikogstreg-online-invitations' )
		);
		echo '</div>';
		echo '</div>';

		printf(
			'<p class="pks-oi-product-envelope-preview__caption">%s</p>',
			esc_html__( 'Guests receive your invitation inside this envelope when you share it online.', 'prikogstreg-online-invitations' )
		);

		if ( '' !== $preset_label || '' !== $background_label ) {
			echo '<p class="pks-oi-product-envelope-preview__meta">';
			if ( '' !== $preset_label ) {
				printf(
					'<span class="pks-oi-product-envelope-preview__label">%s</span> %s',
					esc_html__( 'Style:', 'prikogstreg-online-invitations' ),
					esc_html( (string) $preset_label )
				);
			}
			if ( '' !== $preset_label && '' !== $background_label ) {
				echo ' · ';
			}
			if ( '' !== $background_label ) {
				printf(
					'<span class="pks-oi-product-envelope-preview__label">%s</span> %s',
					esc_html__( 'Background:', 'prikogstreg-online-invitations' ),
					esc_html( (string) $background_label )
				);
			}
			echo '</p>';
		}

		echo '</div>';
		echo '</div>';
		echo '</section>';
	}
}
