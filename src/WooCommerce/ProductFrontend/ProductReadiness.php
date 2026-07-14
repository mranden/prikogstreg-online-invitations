<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\ProductFrontend;

use PrikOgStreg\OnlineInvitations\Admin\Capabilities;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\BuilderValidity;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;

/**
 * Storefront readiness messaging for online_invitation products.
 */
final class ProductReadiness {

	/** @var list<string> */
	private const BUILDER_ERROR_CODES = [
		'bpp_plugin_unavailable',
		'bpp_integration_unavailable',
		'builder_template_missing',
		'builder_pages_missing',
		'bpp_attributes_unresolved',
	];

	public function __construct(
		private BuilderFrontendBridge $builder_bridge
	) {}

	/**
	 * @return list<string>
	 */
	public function error_codes_for_product( object $product ): array {
		if ( ! ProductMeta::is_online_invitation( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return [];
		}

		$errors = BuilderValidity::validation_errors( (int) $product->get_id() );

		if ( method_exists( $product, 'is_purchasable' ) && ! $product->is_purchasable() ) {
			$errors[] = 'product_not_purchasable';
		}

		return array_values( array_unique( $errors ) );
	}

	/**
	 * @return list<array{level:string,message:string}>
	 */
	public function messages_for_product( object $product ): array {
		$error_codes = $this->error_codes_for_product( $product );
		if ( [] === $error_codes ) {
			return [];
		}

		$messages = [];
		foreach ( $error_codes as $code ) {
			$messages[] = [
				'level'   => 'warning',
				'message' => $this->customer_message_for_error_code( $code ),
			];
		}

		/**
		 * @param list<array{level:string,message:string}> $messages
		 */
		$messages = apply_filters( 'pks_oi/product_readiness_messages', $messages, $product );

		return is_array( $messages ) ? $messages : [];
	}

	public function can_view_admin_diagnostics(): bool {
		return function_exists( 'current_user_can' ) && current_user_can( Capabilities::ADMIN_MENU );
	}

	public function render( object $product ): void {
		$error_codes = $this->error_codes_for_product( $product );
		$messages    = $this->messages_for_product( $product );

		if ( [] === $messages && [] === $error_codes ) {
			return;
		}

		$heading_id = 'pks-oi-readiness-heading';

		printf(
			'<section class="pks-oi-product-readiness pks-oi-product-configurator__readiness" aria-labelledby="%1$s" data-pks-oi-section="readiness">',
			esc_attr( $heading_id )
		);
		printf(
			'<h2 class="pks-oi-sr-only" id="%1$s">%2$s</h2>',
			esc_attr( $heading_id ),
			esc_html__( 'Product availability', 'prikogstreg-online-invitations' )
		);

		if ( [] !== $messages ) {
			printf(
				'<div class="pks-oi-product-readiness__summary" role="status" aria-live="polite" aria-atomic="true">'
			);
			echo '<ul class="pks-oi-product-readiness__list">';
			foreach ( $messages as $message ) {
				$level = sanitize_key( (string) ( $message['level'] ?? 'warning' ) );
				printf(
					'<li class="pks-oi-product-readiness__item pks-oi-product-readiness__item--%1$s">%2$s</li>',
					esc_attr( $level ),
					esc_html( (string) ( $message['message'] ?? '' ) )
				);
			}
			echo '</ul>';
			echo '</div>';
		}

		if ( $this->can_view_admin_diagnostics() && [] !== $error_codes ) {
			printf(
				'<aside class="pks-oi-product-readiness__admin" aria-label="%s">',
				esc_attr( __( 'Administrator diagnostics', 'prikogstreg-online-invitations' ) )
			);
			printf(
				'<p class="pks-oi-product-readiness__admin-title">%s</p>',
				esc_html__( 'Administrator diagnostics', 'prikogstreg-online-invitations' )
			);
			echo '<ul class="pks-oi-product-readiness__admin-list">';
			foreach ( $error_codes as $code ) {
				printf(
					'<li class="pks-oi-product-readiness__admin-item"><code>%s</code> — %s</li>',
					esc_html( $code ),
					esc_html( BuilderValidity::error_label( $code ) )
				);
			}
			echo '</ul>';
			echo '</aside>';
		}

		echo '</section>';
	}

	private function customer_message_for_error_code( string $code ): string {
		if ( in_array( $code, self::BUILDER_ERROR_CODES, true ) ) {
			return $this->builder_bridge->customer_readiness_message( $code );
		}

		return match ( $code ) {
			'envelope_preset_missing',
			'envelope_image_invalid',
			'background_preset_missing',
			'price_missing',
			'product_not_purchasable' => __( 'This invitation is not fully set up for purchase yet.', 'prikogstreg-online-invitations' ),
			default => __( 'This invitation cannot be purchased right now.', 'prikogstreg-online-invitations' ),
		};
	}
}
