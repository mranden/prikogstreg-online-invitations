<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\WooCommerce;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\WooCommerce\Checkout\CheckoutBlockGuard;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class CheckoutBlockGuardTest extends TestCase {

	public function test_detects_blocks_checkout_page_from_post_content(): void {
		Functions\when( 'wc_get_page_id' )->justReturn( 42 );
		Functions\when( 'has_block' )->justReturn( false );
		Functions\when( 'get_post_field' )->justReturn( '<!-- wp:woocommerce/checkout /-->' );

		$this->assertTrue( CheckoutBlockGuard::is_blocks_checkout_page() );
	}

	public function test_classic_checkout_page_is_not_blocks(): void {
		Functions\when( 'wc_get_page_id' )->justReturn( 42 );
		Functions\when( 'has_block' )->justReturn( false );
		Functions\when( 'get_post_field' )->justReturn( '[woocommerce_checkout]' );

		$this->assertFalse( CheckoutBlockGuard::is_blocks_checkout_page() );
	}
}
