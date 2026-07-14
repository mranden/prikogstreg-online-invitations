<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\WooCommerce;

use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class WC_Product_Online_InvitationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WC_Product_Simple', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/Support/WC_Product_SimpleStub.php';
		}

		require_once dirname( __DIR__, 3 ) . '/src/WooCommerce/ProductType/WC_Product_Online_Invitation.php';
	}

	public function test_product_is_virtual_and_sold_individually(): void {
		$product = new \WC_Product_Online_Invitation();

		$this->assertSame( 'online_invitation', $product->get_type() );
		$this->assertTrue( $product->is_virtual() );
		$this->assertTrue( $product->is_sold_individually() );
		$this->assertSame( 1, $product->get_min_purchase_quantity() );
		$this->assertSame( 1, $product->get_max_purchase_quantity() );
	}
}
