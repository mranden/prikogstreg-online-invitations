<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Frontend;

use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;
use PrikOgStreg\OnlineInvitations\Support\TemplateVersions;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class AccessibilityTest extends TestCase {

	public function test_public_and_account_templates_are_allowlisted(): void {
		$loader = new TemplateLoader();

		$this->assertStringContainsString( 'templates/public/envelope.php', $loader->locate( 'public/envelope' ) );
		$this->assertStringContainsString( 'templates/public/photos.php', $loader->locate( 'public/photos' ) );
		$this->assertStringContainsString( 'templates/myaccount/project-photos.php', $loader->locate( 'myaccount/project-photos' ) );
	}

	public function test_envelope_template_includes_accessible_fallbacks(): void {
		$path = PKS_OI_PLUGIN_PATH . 'templates/public/envelope.php';
		$this->assertFileExists( $path );
		$contents = (string) file_get_contents( $path );

		$this->assertStringContainsString( '<noscript>', $contents );
		$this->assertStringContainsString( 'aria-label', $contents );
		$this->assertStringContainsString( 'tabindex="-1"', $contents );
		$this->assertStringContainsString( '@version ' . TemplateVersions::VERSION, $contents );
	}

	public function test_rsvp_form_has_labels_and_live_region(): void {
		$path = PKS_OI_PLUGIN_PATH . 'templates/public/rsvp-form.php';
		$contents = (string) file_get_contents( $path );

		$this->assertStringContainsString( '<label', $contents );
		$this->assertStringContainsString( 'aria-live="polite"', $contents );
		$this->assertStringContainsString( 'role="status"', $contents );
	}

	public function test_async_sections_expose_aria_live_status(): void {
		foreach ( [ 'wishlist.php', 'photos.php' ] as $file ) {
			$contents = (string) file_get_contents( PKS_OI_PLUGIN_PATH . 'templates/public/' . $file );
			$this->assertStringContainsString( 'aria-live="polite"', $contents, $file );
			$this->assertStringContainsString( 'role="status"', $contents, $file );
		}
	}

	public function test_production_build_outputs_exist_without_source_maps(): void {
		$root = PKS_OI_PLUGIN_PATH . 'assets/build/';
		foreach ( [ 'css/account.css', 'css/public.css', 'css/admin.css', 'js/account.js', 'js/public.js', 'js/admin.js' ] as $file ) {
			$this->assertFileExists( $root . $file, $file );
			$this->assertFileDoesNotExist( $root . $file . '.map', $file . ' map' );
		}
	}
}
