<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

use PrikOgStreg\OnlineInvitations\Admin\ProjectPostType;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\EnvelopeDesign;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;

/**
 * Creates CPT shells and initial project row payloads.
 */
final class ProjectFactory {

	/**
	 * @param array{
	 *     order_id:int,
	 *     order_item_id:int,
	 *     product_id:int,
	 *     user_id:int,
	 *     product_name:string,
	 *     customer_name:string
	 * } $context
	 */
	public function create_cpt_shell( array $context ): int {
		$title = sprintf(
			/* translators: 1: order ID, 2: product name, 3: customer name */
			__( 'Invitation #%1$d — %2$s — %3$s', 'prikogstreg-online-invitations' ),
			$context['order_id'],
			$context['product_name'],
			$context['customer_name']
		);

		$post_id = wp_insert_post(
			[
				'post_type'   => ProjectPostType::POST_TYPE,
				'post_status' => 'private',
				'post_title'  => $title,
				'post_author' => $context['user_id'],
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			throw new \RuntimeException(
				esc_html( $post_id->get_error_message() ?: 'Failed to create project shell.' )
			);
		}

		return (int) $post_id;
	}

	public function generate_storage_uuid(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}

		$bytes = random_bytes( 16 );
		$bytes[6] = chr( ord( $bytes[6] ) & 0x0f | 0x40 );
		$bytes[8] = chr( ord( $bytes[8] ) & 0x3f | 0x80 );

		return vsprintf(
			'%s%s-%s-%s-%s-%s%s%s',
			str_split( bin2hex( $bytes ), 4 )
		);
	}

	public function generate_generic_token_hash(): string {
		return InvitationToken::generate()['hash'];
	}

	/**
	 * @return array{raw:string,hash:string}
	 */
	public function generate_generic_token_pair(): array {
		return InvitationToken::generate();
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	public function build_initial_row( array $context ): array {
		$product = $context['product'] ?? null;

		return [
			'project_id'               => (int) $context['project_id'],
			'storage_uuid'             => (string) $context['storage_uuid'],
			'user_id'                  => (int) $context['user_id'],
			'order_id'                 => (int) $context['order_id'],
			'order_item_id'            => (int) $context['order_item_id'],
			'product_id'               => (int) $context['product_id'],
			'template_id'              => (string) ( $context['template_id'] ?? (string) $context['product_id'] ),
			'status'                   => ProjectStatus::DRAFT,
			'publication_status'       => PublicationStatus::UNPUBLISHED,
			'locale'                   => is_object( $product ) ? ProductMeta::read_default_locale( $product ) : ProductMeta::DEFAULT_LOCALE_VALUE,
			'timezone'                 => 'Europe/Copenhagen',
			'reminder_offset_days'     => is_object( $product ) ? ProductMeta::read_reminder_offset_days( $product ) : ProductMeta::DEFAULT_REMINDER_OFFSET,
			'guest_photos_enabled'     => is_object( $product ) && ProductMeta::read_guest_photos_default( $product ) ? 1 : 0,
			'internal_wishlist_enabled' => is_object( $product ) && ProductMeta::read_wishlist_default( $product ) ? 1 : 0,
			'envelope_preset'          => is_object( $product ) ? ProductMeta::read_envelope_preset( $product ) : '',
			'background_preset'        => is_object( $product ) ? ProductMeta::read_background_preset( $product ) : '',
			'envelope_image_id'        => is_object( $product ) ? EnvelopeDesign::resolve_for_product( $product )['image_id'] : 0,
			'generic_token_hash'       => (string) $context['generic_token_hash'],
			'generic_token_version'    => 1,
			'builder_schema_version'   => (string) ( $context['builder_schema_version'] ?? '1' ),
			'state_version'            => 0,
			'state_manifest_path'      => '',
			'expires_at_utc'           => $context['expires_at_utc'] ?? null,
		];
	}
}
