<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\ProductFrontend;

use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\AttachmentValidator;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\EnvelopeDesign;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductDummyPreviewController;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;

/**
 * Storefront envelope preview for online_invitation products.
 */
final class EnvelopeFrontend {

	public function __construct(
		private ?BuilderFrontendBridge $builder_bridge = null
	) {}

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
		$product_id       = (int) $product->get_id();
		$page_thumbnails  = $this->builder_bridge?->get_page_thumbnail_map( $product_id ) ?? [];
		$active_page      = $this->builder_bridge?->get_storefront_active_page_index( $product_id ) ?? 0;
		$active_thumbnail = (string) ( $page_thumbnails[ $active_page ] ?? $this->builder_bridge?->get_page_thumbnail( $product_id, $active_page ) ?? '' );

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
		echo '<div class="pks-oi-product-envelope-preview__shell" role="img" aria-label="' . esc_attr( __( 'Envelope artwork preview', 'prikogstreg-online-invitations' ) ) . '"';

		if ( '' !== $image_url ) {
			printf(
				' style="background-image: url(%s);"',
				esc_url( $image_url )
			);
		} else {
			echo ' data-pks-oi-envelope-artwork="fallback"';
		}

		echo '>';

		echo '<div class="pks-oi-product-envelope-preview__inner" data-pks-oi-envelope-thumbnail-host data-pks-oi-active-page="' . esc_attr( (string) $active_page ) . '"';

		if ( [] !== $page_thumbnails ) {
			printf(
				' data-pks-oi-page-thumbnails="%s"',
				esc_attr( (string) wp_json_encode( $page_thumbnails ) )
			);
		}

		echo '>';

		if ( '' !== $active_thumbnail ) {
			printf(
				'<img class="pks-oi-product-envelope-preview__invitation-image" src="%s" alt="%s" loading="lazy" decoding="async" />',
				esc_attr( $this->escape_thumbnail_src( $active_thumbnail ) ),
				esc_attr__( 'Invitation preview', 'prikogstreg-online-invitations' )
			);
		} else {
			printf(
				'<span class="pks-oi-product-envelope-preview__inner-label">%s</span>',
				esc_html__( 'Your designed invitation goes inside', 'prikogstreg-online-invitations' )
			);
		}

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

		if ( ProductMeta::read_dummy_preview_enabled( $product ) ) {
			$sample_url = ProductDummyPreviewController::preview_url( $product_id );
			if ( '' !== $sample_url ) {
				printf(
					'<p class="pks-oi-product-envelope-preview__sample-link"><a class="button button-secondary" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></p>',
					esc_url( $sample_url ),
					esc_html__( 'See sample invitation', 'prikogstreg-online-invitations' )
				);
			}
		}

		echo '</div>';
		echo '</div>';
		echo '</section>';
	}

	/**
	 * BPP thumbnails may be https URLs or admin-generated base64 data URLs.
	 */
	private function escape_thumbnail_src( string $url ): string {
		if ( str_starts_with( $url, 'data:image/' ) ) {
			return $url;
		}

		return (string) esc_url( $url );
	}
}
