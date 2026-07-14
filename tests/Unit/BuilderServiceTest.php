<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;
use Brain\Monkey\Functions;

final class BuilderServiceTest extends TestCase {

	public function test_adapter_unavailable_when_filter_not_registered(): void {
		Functions\when( 'has_filter' )->justReturn( false );

		$service = new BuilderService();
		$service->resolve();

		$this->assertFalse( $service->is_available() );
		$this->assertNull( $service->get_adapter() );
	}

	public function test_adapter_unavailable_when_filter_returns_null(): void {
		Functions\when( 'has_filter' )->justReturn( true );
		Functions\when( 'apply_filters' )->justReturn( null );

		$service = new BuilderService();
		$service->resolve();

		$this->assertFalse( $service->is_available() );
	}
}
