<?php
/**
 * Online invitation add-to-cart form.
 *
 * Override (optional):
 * - child-theme/prikogstreg-online-invitations/product/add-to-cart-online-invitation.php
 * - child-theme/woocommerce/single-product/add-to-cart/online-invitation.php
 *
 * Business logic lives in plugin services — keep this template presentational.
 *
 * @var \WC_Product $product
 * @var \PrikOgStreg\OnlineInvitations\WooCommerce\ProductFrontend\OnlineInvitationProductFrontend $pks_oi_product_frontend
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_add_to_cart_form' );
?>

<form
	class="cart pks-oi-add-to-cart pks-oi-product-configurator"
	action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>"
	method="post"
	enctype="multipart/form-data"
	data-pks-oi-product-form="online-invitation"
	aria-describedby="pks-oi-configurator-description"
>
	<p class="pks-oi-sr-only" id="pks-oi-configurator-description">
		<?php echo esc_html__( 'Design your invitation, preview it, then add it to your cart.', 'prikogstreg-online-invitations' ); ?>
	</p>

	<?php $pks_oi_product_frontend->render_envelope_section( $product ); ?>

	<?php $pks_oi_product_frontend->render_canvas_hint( $product ); ?>

	<?php $pks_oi_product_frontend->render_future_options( $product ); ?>

	<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

	<section
		class="pks-oi-product-configurator__section pks-oi-product-configurator__controls"
		aria-labelledby="pks-oi-controls-heading"
		data-pks-oi-section="builder-controls"
	>
		<h2 class="pks-oi-product-configurator__section-title" id="pks-oi-controls-heading">
			<?php echo esc_html__( 'Customize your invitation', 'prikogstreg-online-invitations' ); ?>
		</h2>
		<div class="pks-oi-product-configurator__builder-fields" data-pks-oi-section="builder-fields">
			<?php $pks_oi_product_frontend->render_builder_fields( $product ); ?>
		</div>
	</section>

	<?php do_action( 'woocommerce_before_add_to_cart_quantity' ); ?>

	<input type="hidden" name="quantity" value="1" />

	<section
		class="pks-oi-product-configurator__section pks-oi-product-configurator__preview"
		aria-labelledby="pks-oi-preview-heading"
		data-pks-oi-section="preview-actions"
	>
		<h2 class="pks-oi-sr-only" id="pks-oi-preview-heading">
			<?php echo esc_html__( 'Preview', 'prikogstreg-online-invitations' ); ?>
		</h2>
		<?php do_action( 'woocommerce_after_add_to_cart_quantity' ); ?>
	</section>

	<div class="pks-oi-product-configurator__loading" hidden role="status" aria-live="polite">
		<?php echo esc_html__( 'Adding your invitation to the cart…', 'prikogstreg-online-invitations' ); ?>
	</div>

	<section
		class="pks-oi-product-configurator__section pks-oi-product-configurator__purchase"
		aria-labelledby="pks-oi-purchase-heading"
		data-pks-oi-section="purchase-actions"
	>
		<h2 class="pks-oi-sr-only" id="pks-oi-purchase-heading">
			<?php echo esc_html__( 'Purchase', 'prikogstreg-online-invitations' ); ?>
		</h2>
		<?php $pks_oi_product_frontend->render_native_purchase_button( $product ); ?>
	</section>

	<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
</form>

<?php
do_action( 'woocommerce_after_add_to_cart_form' );
