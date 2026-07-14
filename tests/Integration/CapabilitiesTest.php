<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Admin\Capabilities;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class CapabilitiesTest extends TestCase {

	public function test_registers_support_and_manage_caps_for_roles(): void {
		$admin_caps     = [];
		$shop_caps      = [];
		$customer_caps  = [];

		$admin_role = new class( $admin_caps ) {
			public function __construct( private array &$caps ) {}

			public function add_cap( string $cap ): void {
				$this->caps[] = $cap;
			}
		};

		$shop_role = new class( $shop_caps ) {
			public function __construct( private array &$caps ) {}

			public function add_cap( string $cap ): void {
				$this->caps[] = $cap;
			}
		};

		$customer_role = new class( $customer_caps ) {
			public function __construct( private array &$caps ) {}

			public function add_cap( string $cap ): void {
				$this->caps[] = $cap;
			}
		};

		Functions\when( 'get_role' )->alias(
			static function ( string $role ) use ( $admin_role, $shop_role, $customer_role ) {
				return match ( $role ) {
					'administrator' => $admin_role,
					'shop_manager'  => $shop_role,
					'customer'      => $customer_role,
					default         => null,
				};
			}
		);

		Capabilities::register_for_roles();

		$this->assertContains( Capabilities::SUPPORT, $admin_caps );
		$this->assertContains( Capabilities::MANAGE_OWN, $admin_caps );
		$this->assertContains( Capabilities::SUPPORT, $shop_caps );
		$this->assertNotContains( Capabilities::MANAGE_OWN, $shop_caps );
		$this->assertContains( Capabilities::MANAGE_OWN, $customer_caps );
	}
}
