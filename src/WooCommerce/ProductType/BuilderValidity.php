<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\ProductType;

/**
 * Validates builder template and invitation product configuration.
 */
final class BuilderValidity {

	/**
	 * @return list<string>
	 */
	public static function validation_errors( int $product_id ): array {
		$errors = [];

		if ( ! self::has_active_builder_template( $product_id ) ) {
			$errors[] = 'builder_template_missing';
		}

		$envelope = (string) get_post_meta( $product_id, ProductMeta::ENVELOPE_PRESET, true );
		if ( '' === $envelope || ! ProductMeta::is_envelope_preset_valid( $envelope ) ) {
			$errors[] = 'envelope_preset_missing';
		}

		$background = (string) get_post_meta( $product_id, ProductMeta::BACKGROUND_PRESET, true );
		if ( '' === $background || ! ProductMeta::is_background_preset_valid( $background ) ) {
			$errors[] = 'background_preset_missing';
		}

		return $errors;
	}

	public static function is_valid( int $product_id ): bool {
		return [] === self::validation_errors( $product_id );
	}

	public static function has_active_builder_template( int $product_id ): bool {
		if ( class_exists( 'BPP_Product' ) ) {
			$builder = new \BPP_Product( $product_id );

			return ! empty( $builder->active );
		}

		$meta = get_post_meta( $product_id, ProductMeta::BUILDER_META_KEY, true );
		if ( ! is_object( $meta ) ) {
			return false;
		}

		return ! empty( $meta->active );
	}

	/**
	 * @return array{status:string,label:string,detail:string}
	 */
	public static function integration_status( int $product_id ): array {
		if ( self::is_valid( $product_id ) ) {
			return [
				'status' => 'ready',
				'label'  => __( 'Ready for purchase', 'prikogstreg-online-invitations' ),
				'detail' => __( 'Builder template and invitation presets are configured.', 'prikogstreg-online-invitations' ),
			];
		}

		$errors = self::validation_errors( $product_id );
		$labels = array_map( [ self::class, 'error_label' ], $errors );

		return [
			'status' => 'incomplete',
			'label'  => __( 'Configuration incomplete', 'prikogstreg-online-invitations' ),
			'detail' => implode( ' ', $labels ),
		];
	}

	public static function error_label( string $code ): string {
		return match ( $code ) {
			'builder_template_missing' => __( 'Activate and save a PDF Builder template for this product.', 'prikogstreg-online-invitations' ),
			'envelope_preset_missing'  => __( 'Select a valid envelope preset.', 'prikogstreg-online-invitations' ),
			'background_preset_missing' => __( 'Select a valid background preset.', 'prikogstreg-online-invitations' ),
			default => __( 'Invitation configuration is incomplete.', 'prikogstreg-online-invitations' ),
		};
	}
}
