<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\Public;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Public\Endpoints;
use PrikOgStreg\OnlineInvitations\Public\PhotoShareEndpoints;
use PrikOgStreg\OnlineInvitations\Public\PublicAssetManager;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class PublicAssetManagerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_dequeue_style' )->justReturn( true );
		Functions\when( 'wp_deregister_style' )->justReturn( true );
		Functions\when( 'wp_dequeue_script' )->justReturn( true );
		Functions\when( 'wp_deregister_script' )->justReturn( true );
		Functions\when( 'remove_action' )->justReturn( true );
		Functions\when( 'do_action' )->justReturn( null );
	}

	public function test_dequeues_handles_on_invitation_page(): void {
		$dequeued = [];

		Functions\when( 'get_query_var' )->alias(
			function ( string $key ) {
				if ( Endpoints::QUERY_VAR === $key ) {
					return 'abc123token';
				}
				if ( Endpoints::POSTER_ASSET_QUERY_VAR === $key ) {
					return '';
				}
				if ( Endpoints::ENVELOPE_ASSET_QUERY_VAR === $key ) {
					return '';
				}

				return '';
			}
		);

		Functions\when( 'wp_dequeue_style' )->alias(
			function ( string $handle ) use ( &$dequeued ): bool {
				$dequeued[] = 'style:' . $handle;
				return true;
			}
		);
		Functions\when( 'wp_dequeue_script' )->alias(
			function ( string $handle ) use ( &$dequeued ): bool {
				$dequeued[] = 'script:' . $handle;
				return true;
			}
		);

		( new PublicAssetManager() )->dequeue_unrelated_assets();

		$this->assertContains( 'script:minicart-js', $dequeued );
		$this->assertContains( 'style:theme-style', $dequeued );
	}

	public function test_does_not_dequeue_on_non_invitation_route(): void {
		$dequeued = [];

		Functions\when( 'get_query_var' )->justReturn( '' );
		Functions\when( 'wp_dequeue_style' )->alias(
			function ( string $handle ) use ( &$dequeued ): bool {
				$dequeued[] = $handle;
				return true;
			}
		);

		( new PublicAssetManager() )->dequeue_unrelated_assets();

		$this->assertSame( [], $dequeued );
	}

	public function test_dequeues_handles_on_photo_share_page(): void {
		$dequeued = [];

		Functions\when( 'get_query_var' )->alias(
			function ( string $key ) {
				if ( PhotoShareEndpoints::QUERY_VAR === $key ) {
					return 'share-token-value';
				}

				return '';
			}
		);

		Functions\when( 'wp_dequeue_style' )->alias(
			function ( string $handle ) use ( &$dequeued ): bool {
				$dequeued[] = 'style:' . $handle;
				return true;
			}
		);

		( new PublicAssetManager() )->dequeue_unrelated_assets();

		$this->assertContains( 'style:admin-bar', $dequeued );
		$this->assertContains( 'style:prikogstreg-fonts', $dequeued );
	}
}
