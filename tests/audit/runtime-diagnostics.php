<?php
/**
 * Read-only audit diagnostic script. Does not modify production data.
 *
 * Run: php tests/audit/runtime-diagnostics.php
 *
 * Created for online-invitation integration audit (2026-07-14).
 */

declare(strict_types=1);

$wp_load = dirname( __DIR__, 5 ) . '/wp-load.php';
if ( ! is_file( $wp_load ) ) {
	fwrite( STDERR, "wp-load.php not found at {$wp_load}\n" );
	exit( 1 );
}

require $wp_load;

global $wpdb;

$report = [
	'wordpress_version' => get_bloginfo( 'version' ),
	'woocommerce_version' => defined( 'WC_VERSION' ) ? WC_VERSION : null,
	'php_version' => PHP_VERSION,
	'hpos_enabled' => class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
		? \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
		: null,
	'pdf_plugin_active' => class_exists( 'BPP_PDF_Plugin', false ),
	'oi_plugin_active' => defined( 'PKS_OI_VERSION' ) ? PKS_OI_VERSION : null,
	'checkout' => [],
	'product_types' => [],
	'online_invitation_products' => [],
	'bpp_products' => [],
];

$checkout_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'checkout' ) : 0;
if ( $checkout_id > 0 ) {
	$content = (string) get_post_field( 'post_content', $checkout_id );
	$report['checkout'] = [
		'page_id' => $checkout_id,
		'template' => get_page_template_slug( $checkout_id ),
		'has_checkout_block' => str_contains( $content, 'wp:woocommerce/checkout' ),
		'has_classic_shortcode' => str_contains( $content, '[woocommerce_checkout]' ),
	];
}

$type_rows = $wpdb->get_results(
	"SELECT t.slug, COUNT(*) AS c
	 FROM {$wpdb->term_relationships} tr
	 JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
	 JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
	 WHERE tt.taxonomy = 'product_type'
	 GROUP BY t.slug",
	ARRAY_A
);
$report['product_types'] = $type_rows ?: [];

$oi_ids = $wpdb->get_col(
	"SELECT p.ID
	 FROM {$wpdb->posts} p
	 JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
	 JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
	 JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
	 WHERE p.post_type = 'product'
	   AND p.post_status IN ('publish','draft','private')
	   AND tt.taxonomy = 'product_type'
	   AND t.slug = 'online_invitation'"
);

foreach ( $oi_ids as $product_id ) {
	$product_id = (int) $product_id;
	$bpp_raw = get_post_meta( $product_id, '_bpp_product', true );
	$report['online_invitation_products'][] = [
		'id' => $product_id,
		'title' => get_the_title( $product_id ),
		'status' => get_post_status( $product_id ),
		'has_bpp_meta' => ! empty( $bpp_raw ),
		'bpp_active' => class_exists( 'BPP_Product', false )
			? (bool) ( new BPP_Product( $product_id ) )->active
			: null,
	];
}

$bpp_ids = $wpdb->get_col(
	"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_bpp_product' AND meta_value != '' LIMIT 20"
);
foreach ( $bpp_ids as $product_id ) {
	$product_id = (int) $product_id;
	$terms = wp_get_object_terms( $product_id, 'product_type', [ 'fields' => 'slugs' ] );
	$report['bpp_products'][] = [
		'id' => $product_id,
		'title' => get_the_title( $product_id ),
		'product_type' => is_array( $terms ) ? $terms : [],
		'bpp_active' => class_exists( 'BPP_Product', false )
			? (bool) ( new BPP_Product( $product_id ) )->active
			: null,
	];
}

echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
