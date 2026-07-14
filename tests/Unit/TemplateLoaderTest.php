<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit;

use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class TemplateLoaderTest extends TestCase {

	public function test_rejects_non_allowlisted_template_paths(): void {
		$loader = new TemplateLoader();

		$this->assertSame( '', $loader->locate( '../evil' ) );
		$this->assertSame( '', $loader->locate( 'not-allowed/template' ) );
	}

	public function test_allowlisted_template_resolves_plugin_path_when_readable(): void {
		$loader = new TemplateLoader();
		$path   = $loader->locate( 'myaccount/dashboard' );

		$this->assertStringContainsString( 'templates/myaccount/dashboard.php', $path );
	}

	public function test_public_photos_template_is_allowlisted(): void {
		$loader = new TemplateLoader();
		$path   = $loader->locate( 'public/photos' );

		$this->assertStringContainsString( 'templates/public/photos.php', $path );
	}
}
