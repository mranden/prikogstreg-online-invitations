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
		$customize  = admin_url( 'admin.php?page=bpp-customize&product_id=' . $product_id );
		?>
		<div id="pks_oi_online_invitation_product_data" class="panel woocommerce_options_panel hidden">
			<div class="options_group pks-oi-admin">
				<p class="form-field">
					<strong><?php esc_html_e( 'Builder integration status', 'prikogstreg-online-invitations' ); ?></strong><br />
					<span class="pks-oi-admin-status pks-oi-admin-status--<?php echo esc_attr( $status['status'] ); ?>">
						<?php echo esc_html( $status['label'] ); ?>
					</span><br />
					<span class="description"><?php echo esc_html( $status['detail'] ); ?></span><br />
					<a href="<?php echo esc_url( $customize ); ?>"><?php esc_html_e( 'Open PDF Builder customizer', 'prikogstreg-online-invitations' ); ?></a>
				</p>

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

				woocommerce_wp_text_input(
					[
						'id'          => ProductMeta::ENVELOPE_PREVIEW_REF,
						'label'       => __( 'Envelope preview reference', 'prikogstreg-online-invitations' ),
						'value'       => (string) get_post_meta( $product_id, ProductMeta::ENVELOPE_PREVIEW_REF, true ),
						'desc_tip'    => true,
						'description' => __( 'Optional admin reference for preview assets (slug, note, or internal asset key).', 'prikogstreg-online-invitations' ),
					]
				);

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

		wp_enqueue_style(
			'pks-oi-admin',
			PKS_OI_PLUGIN_URL . 'assets/build/css/admin.css',
			[],
			PKS_OI_VERSION
		);

		wp_enqueue_script(
			'pks-oi-admin',
			PKS_OI_PLUGIN_URL . 'assets/build/js/admin.js',
			[],
			PKS_OI_VERSION,
			true
		);
	}
}
