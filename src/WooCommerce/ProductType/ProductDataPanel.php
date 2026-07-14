<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\ProductType;

/**
 * WooCommerce admin product data panel for online_invitation settings.
 */
final class ProductDataPanel {

	public function register(): void {
		add_filter( 'woocommerce_product_data_tabs', [ $this, 'register_tab' ] );
		add_action( 'woocommerce_product_data_panels', [ $this, 'render_panel' ] );
		add_action( 'woocommerce_process_product_meta', [ $this, 'save_product_meta' ] );
		add_action( 'admin_notices', [ $this, 'render_admin_validation_notice' ] );
		add_filter( 'wp_insert_post_data', [ $this, 'prevent_invalid_publish' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	/**
	 * @param array<string, array<string, mixed>> $tabs
	 * @return array<string, array<string, mixed>>
	 */
	public function register_tab( array $tabs ): array {
		$tabs['pks_oi_invitation'] = [
			'label'    => __( 'Online Invitation', 'prikogstreg-online-invitations' ),
			'target'   => 'pks_oi_online_invitation_product_data',
			'class'    => [ 'show_if_' . ProductMeta::TYPE ],
			'priority' => 21,
		];

		return $tabs;
	}

	public function render_panel(): void {
		global $post;

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$product_id = (int) $post->ID;
		$status     = BuilderValidity::integration_status( $product_id );
		$customize  = admin_url( 'admin.php?page=bpp-customize&prdid=' . $product_id );
		$product    = wc_get_product( $product_id );
		$design     = $product ? EnvelopeDesign::resolve_for_product( $product ) : [
			'preset'            => '',
			'background_preset' => '',
			'image_id'          => 0,
			'image_url'         => '',
			'image_source'      => EnvelopeDesign::SOURCE_PRESET,
			'explicit_image_id' => 0,
		];
		$envelope_image_id = $product
			? ProductMeta::read_envelope_image_id( $product )
			: max( 0, (int) get_post_meta( $product_id, ProductMeta::ENVELOPE_IMAGE_ID, true ) );
		$preview_url       = $envelope_image_id > 0
			? AttachmentValidator::image_url( $envelope_image_id, 'medium' )
			: (string) ( $design['image_url'] ?? '' );
		?>
		<div id="pks_oi_online_invitation_product_data" class="panel woocommerce_options_panel hidden">
			<div class="options_group pks-oi-admin">
				<p class="form-field">
					<strong><?php esc_html_e( 'Builder integration status', 'prikogstreg-online-invitations' ); ?></strong><br />
					<span class="pks-oi-admin-status pks-oi-admin-status--<?php echo esc_attr( $status['status'] ); ?>">
						<?php echo esc_html( $status['label'] ); ?>
					</span><br />
					<span class="description"><?php echo esc_html( $status['detail'] ); ?></span><br />
					<?php if ( ! ProductMeta::is_builder_optional_id( $product_id ) ) : ?>
						<a href="<?php echo esc_url( $customize ); ?>"><?php esc_html_e( 'Open PDF Builder customizer', 'prikogstreg-online-invitations' ); ?></a>
					<?php endif; ?>
				</p>

				<?php
				woocommerce_wp_checkbox(
					[
						'id'          => ProductMeta::BUILDER_OPTIONAL,
						'label'       => __( 'PDF Builder optional (testing)', 'prikogstreg-online-invitations' ),
						'value'       => wc_bool_to_string( ProductMeta::is_builder_optional_id( $product_id ) ),
						'description' => __( 'Allow purchase without a connected PDF Builder template. The product page shows a placeholder preview during testing.', 'prikogstreg-online-invitations' ),
					]
				);
				?>

				<?php
				woocommerce_wp_select(
					[
						'id'          => ProductMeta::ENVELOPE_PRESET,
						'label'       => __( 'Envelope preset', 'prikogstreg-online-invitations' ),
						'options'     => [ '' => __( 'Select a preset…', 'prikogstreg-online-invitations' ) ] + ProductMeta::envelope_presets(),
						'value'       => (string) get_post_meta( $product_id, ProductMeta::ENVELOPE_PRESET, true ),
						'desc_tip'    => true,
						'description' => __( 'Controls the animated envelope style on the public invitation.', 'prikogstreg-online-invitations' ),
					]
				);

				?>
				<p class="form-field pks-oi-envelope-image-field">
					<label for="<?php echo esc_attr( ProductMeta::ENVELOPE_IMAGE_ID ); ?>">
						<?php esc_html_e( 'Envelope image', 'prikogstreg-online-invitations' ); ?>
					</label>
					<input
						type="hidden"
						name="<?php echo esc_attr( ProductMeta::ENVELOPE_IMAGE_ID ); ?>"
						id="<?php echo esc_attr( ProductMeta::ENVELOPE_IMAGE_ID ); ?>"
						value="<?php echo esc_attr( (string) $envelope_image_id ); ?>"
					/>
					<span class="description">
						<?php esc_html_e( 'Optional custom image for the closed envelope card. When empty, the first product gallery image may be used as a fallback. The featured image remains the shop thumbnail.', 'prikogstreg-online-invitations' ); ?>
					</span>
					<span class="pks-oi-envelope-image-actions">
						<button type="button" class="button pks-oi-envelope-image-upload">
							<?php esc_html_e( 'Select image', 'prikogstreg-online-invitations' ); ?>
						</button>
						<button type="button" class="button pks-oi-envelope-image-remove" <?php echo $envelope_image_id > 0 ? '' : 'style="display:none"'; ?>>
							<?php esc_html_e( 'Remove image', 'prikogstreg-online-invitations' ); ?>
						</button>
					</span>
					<span
						class="pks-oi-envelope-image-preview"
						<?php echo '' !== $preview_url ? '' : 'style="display:none"'; ?>
					>
						<img src="<?php echo esc_url( $preview_url ); ?>" alt="" />
					</span>
				</p>
				<div class="pks-oi-envelope-admin-preview">
					<strong><?php esc_html_e( 'Envelope preview', 'prikogstreg-online-invitations' ); ?></strong>
					<div
						class="pks-oi-envelope-admin-preview__frame pks-oi-envelope--<?php echo esc_attr( (string) ( $design['preset'] ?: 'classic' ) ); ?> pks-oi-envelope--bg-<?php echo esc_attr( (string) ( $design['background_preset'] ?: 'neutral' ) ); ?>"
					>
						<div class="pks-oi-envelope-admin-preview__card">
							<?php if ( '' !== (string) ( $design['image_url'] ?? '' ) ) : ?>
								<img src="<?php echo esc_url( (string) $design['image_url'] ); ?>" alt="" />
							<?php endif; ?>
							<p><?php esc_html_e( 'You are invited', 'prikogstreg-online-invitations' ); ?></p>
						</div>
					</div>
					<p class="description">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: image source label */
								__( 'Resolved image source: %s', 'prikogstreg-online-invitations' ),
								EnvelopeDesign::image_source_label( (string) ( $design['image_source'] ?? EnvelopeDesign::SOURCE_PRESET ) )
							)
						);
						?>
					</p>
				</div>
				<?php

				woocommerce_wp_select(
					[
						'id'          => ProductMeta::BACKGROUND_PRESET,
						'label'       => __( 'Generic background preset', 'prikogstreg-online-invitations' ),
						'options'     => [ '' => __( 'Select a preset…', 'prikogstreg-online-invitations' ) ] + ProductMeta::background_presets(),
						'value'       => (string) get_post_meta( $product_id, ProductMeta::BACKGROUND_PRESET, true ),
					]
				);

				woocommerce_wp_text_input(
					[
						'id'    => ProductMeta::DEFAULT_LOCALE,
						'label' => __( 'Default locale', 'prikogstreg-online-invitations' ),
						'value' => (string) get_post_meta( $product_id, ProductMeta::DEFAULT_LOCALE, true ) ?: ProductMeta::DEFAULT_LOCALE_VALUE,
					]
				);

				woocommerce_wp_text_input(
					[
						'id'                => ProductMeta::REMINDER_OFFSET_DAYS,
						'label'             => __( 'RSVP reminder offset (days)', 'prikogstreg-online-invitations' ),
						'type'              => 'number',
						'custom_attributes' => [
							'min'  => '1',
							'max'  => '30',
							'step' => '1',
						],
						'value'             => (string) ( get_post_meta( $product_id, ProductMeta::REMINDER_OFFSET_DAYS, true ) ?: ProductMeta::DEFAULT_REMINDER_OFFSET ),
					]
				);

				woocommerce_wp_checkbox(
					[
						'id'          => ProductMeta::GUEST_PHOTOS_DEFAULT,
						'label'       => __( 'Guest photo uploads enabled by default', 'prikogstreg-online-invitations' ),
						'value'       => wc_bool_to_string( 'yes' === get_post_meta( $product_id, ProductMeta::GUEST_PHOTOS_DEFAULT, true ) || '' === get_post_meta( $product_id, ProductMeta::GUEST_PHOTOS_DEFAULT, true ) ),
					]
				);

				woocommerce_wp_checkbox(
					[
						'id'          => ProductMeta::WISHLIST_DEFAULT,
						'label'       => __( 'Internal wishlist enabled by default', 'prikogstreg-online-invitations' ),
						'value'       => wc_bool_to_string( 'yes' === get_post_meta( $product_id, ProductMeta::WISHLIST_DEFAULT, true ) || '' === get_post_meta( $product_id, ProductMeta::WISHLIST_DEFAULT, true ) ),
					]
				);
				?>
			</div>
		</div>
		<?php
	}

	public function save_product_meta( int $post_id ): void {
		$product = wc_get_product( $post_id );
		if ( ! $product || ! ProductMeta::is_online_invitation( $product ) ) {
			return;
		}

		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}

		ProductMeta::ensure_defaults( $product );
		ProductMeta::save_admin_fields( $product, wp_unslash( $_POST ) );
		$product->save();
	}

	public function render_admin_validation_notice(): void {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		if ( $post_id <= 0 ) {
			return;
		}

		$product = wc_get_product( $post_id );
		if ( ! $product || ! ProductMeta::is_online_invitation( $product ) ) {
			return;
		}

		if ( BuilderValidity::is_valid( $post_id ) ) {
			return;
		}

		$status = BuilderValidity::integration_status( $post_id );
		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'Online Invitation product is incomplete:', 'prikogstreg-online-invitations' ),
			esc_html( $status['detail'] )
		);
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $postarr
	 * @return array<string, mixed>
	 */
	public function prevent_invalid_publish( array $data, array $postarr ): array {
		if ( 'product' !== ( $data['post_type'] ?? '' ) || 'publish' !== ( $data['post_status'] ?? '' ) ) {
			return $data;
		}

		$post_id = (int) ( $postarr['ID'] ?? 0 );
		if ( $post_id <= 0 ) {
			return $data;
		}

		$product = wc_get_product( $post_id );
		if ( ! $product || ! ProductMeta::is_online_invitation( $product ) ) {
			return $data;
		}

		if ( BuilderValidity::is_valid( $post_id ) ) {
			return $data;
		}

		$data['post_status'] = 'draft';

		add_filter(
			'redirect_post_location',
			static function ( string $location ) use ( $post_id ): string {
				return add_query_arg(
					[
						'pks_oi_invalid_publish' => '1',
						'post'                   => $post_id,
					],
					$location
				);
			}
		);

		return $data;
	}

	public function enqueue_admin_assets( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'pks-oi-admin',
			PKS_OI_PLUGIN_URL . 'assets/build/css/admin.css',
			[],
			PKS_OI_VERSION
		);

		wp_enqueue_script(
			'pks-oi-admin',
			PKS_OI_PLUGIN_URL . 'assets/build/js/admin.js',
			[ 'jquery', 'wc-admin-meta-boxes' ],
			PKS_OI_VERSION,
			true
		);

		wp_localize_script(
			'pks-oi-admin',
			'pksOiAdmin',
			[
				'productType' => ProductMeta::TYPE,
			]
		);
	}
}
