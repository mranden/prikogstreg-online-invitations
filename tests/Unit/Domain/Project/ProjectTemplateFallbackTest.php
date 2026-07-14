<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\Domain\Project;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectDesignSource;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectTemplateFallback;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeBuilderAdapter;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class ProjectTemplateFallbackTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 3 ) . '/stubs/bpp/Builder_Adapter_Interface.php';

		Functions\when( 'has_filter' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				if ( 'bpp/integration/service' === $hook ) {
					return new FakeBuilderAdapter();
				}

				return $value;
			}
		);
	}

	public function test_recoverable_import_errors_include_missing_page_payload(): void {
		$this->assertTrue( ProjectTemplateFallback::is_recoverable_import_error( 'missing_page_payload' ) );
		$this->assertFalse( ProjectTemplateFallback::is_recoverable_import_error( 'checksum_mismatch' ) );
	}

	public function test_resolve_for_product_marks_template_fallback_with_placeholder_pages(): void {
		$builder = new BuilderService();
		$builder->resolve();

		$fallback = new ProjectTemplateFallback( $builder );
		$state    = $fallback->resolve_for_product( 42 );

		$this->assertSame( ProjectDesignSource::TEMPLATE_FALLBACK, $state['design_source'] ?? '' );
		$this->assertNotEmpty( $state['page'] ?? [] );
		$this->assertStringContainsString( 'pks-oi-template-fallback', (string) ( $state['page'][0] ?? '' ) );
	}
}
