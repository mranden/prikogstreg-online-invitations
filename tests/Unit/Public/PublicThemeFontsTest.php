<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\Public;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Public\PublicThemeFonts;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class PublicThemeFontsTest extends TestCase {

	public function test_enqueues_theme_fonts_with_plugin_handle(): void {
		$enqueued = [];

		Functions\when( 'get_template_directory_uri' )->justReturn( 'https://example.test/wp-content/themes/prikogstreg' );
		Functions\when( 'wp_enqueue_style' )->alias(
			function ( string $handle, string $src, array $deps, $ver ) use ( &$enqueued ): bool {
				$enqueued[] = compact( 'handle', 'src', 'deps', 'ver' );
				return true;
			}
		);

		PublicThemeFonts::enqueue();

		$this->assertCount( 1, $enqueued );
		$this->assertSame( 'pks-oi-theme-fonts', $enqueued[0]['handle'] );
		$this->assertSame(
			'https://example.test/wp-content/themes/prikogstreg/assets/fonts/fonts.css',
			$enqueued[0]['src']
		);
	}
}
