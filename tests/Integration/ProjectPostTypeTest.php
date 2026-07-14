<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Admin\Capabilities;
use PrikOgStreg\OnlineInvitations\Admin\ProjectPostType;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class ProjectPostTypeTest extends TestCase {

	/** @var array<string, mixed>|null */
	private ?array $registered = null;

	protected function setUp(): void {
		parent::setUp();

		$this->registered = null;

		Functions\when( 'register_post_type' )->alias(
			function ( string $post_type, array $args ): void {
				$this->registered = array_merge( [ 'post_type' => $post_type ], $args );
			}
		);
	}

	public function test_registers_private_non_queryable_post_type(): void {
		( new ProjectPostType() )->register_post_type();

		$this->assertIsArray( $this->registered );
		$this->assertSame( ProjectPostType::POST_TYPE, $this->registered['post_type'] );
		$this->assertFalse( $this->registered['public'] );
		$this->assertFalse( $this->registered['publicly_queryable'] );
		$this->assertFalse( $this->registered['rewrite'] );
		$this->assertFalse( $this->registered['query_var'] );
	}

	public function test_maps_admin_caps_to_support_capability(): void {
		( new ProjectPostType() )->register_post_type();

		$this->assertIsArray( $this->registered );
		$this->assertSame( Capabilities::SUPPORT, $this->registered['capabilities']['edit_post'] );
		$this->assertSame( Capabilities::SUPPORT, $this->registered['capabilities']['delete_post'] );
	}
}
