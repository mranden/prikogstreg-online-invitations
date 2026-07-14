<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Public;

use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class PublicControllerTest extends TestCase {

	public function test_privacy_headers_are_declared_in_controller(): void {
		$source = (string) file_get_contents( PKS_OI_PLUGIN_PATH . 'src/Public/PublicController.php' );

		$this->assertStringContainsString( "header( 'X-Robots-Tag: noindex, nofollow'", $source );
		$this->assertStringContainsString( "header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0'", $source );
		$this->assertStringContainsString( "header( 'Pragma: no-cache'", $source );
		$this->assertStringContainsString( 'status_header( 404 )', $source );
	}
}
