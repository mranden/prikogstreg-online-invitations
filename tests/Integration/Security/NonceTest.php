<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Security;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\MyAccount\ProjectController;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class NonceTest extends TestCase {

	protected function tearDown(): void {
		unset( $_POST );
		parent::tearDown();
	}

	public function test_missing_nonce_fails_verification(): void {
		$_POST = [];
		Functions\when( 'wp_verify_nonce' )->justReturn( false );

		$this->assertFalse( ProjectController::verify_nonce() );
	}

	public function test_invalid_nonce_fails_verification(): void {
		$_POST = [ 'pks_oi_nonce' => 'bad-nonce' ];
		Functions\when( 'wp_verify_nonce' )->justReturn( false );

		$this->assertFalse( ProjectController::verify_nonce() );
	}

	public function test_valid_nonce_passes_verification(): void {
		$_POST = [ 'pks_oi_nonce' => 'valid-nonce' ];
		Functions\when( 'wp_verify_nonce' )->alias(
			static fn( string $nonce, string $action ) => 'valid-nonce' === $nonce
				&& ProjectController::NONCE_ACTION === $action
		);

		$this->assertTrue( ProjectController::verify_nonce() );
	}
}
