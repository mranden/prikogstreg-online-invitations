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
		$this->assertTrue( ProjectTemplateFallback::is_recoverable_import_error( 'checksum_mismatch' ) );
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

	public function test_design_placeholder_page_html_uses_bpp_page_zero_thumbnail(): void {
		require_once dirname( __DIR__, 3 ) . '/stubs/bpp/BPP_Product.php';

		\BPP_Product::set_test_model(
			10,
			(object) [
				'active' => true,
				'pages'  => [
					(object) [
						'thumbnail' => 'https://example.test/page-thumbnail-0.jpg',
					],
				],
			]
		);

		$fallback = new ProjectTemplateFallback( new BuilderService() );
		$html     = $fallback->design_placeholder_page_html( 10 );

		$this->assertStringContainsString( 'pks-oi-design-placeholder__image', $html );
		$this->assertStringContainsString( 'https://example.test/page-thumbnail-0.jpg', $html );
		$this->assertStringNotContainsString( '<p>Your invitation design will appear here.</p>', $html );
	}

	public function test_design_placeholder_page_html_falls_back_to_text_without_thumbnail(): void {
		require_once dirname( __DIR__, 3 ) . '/stubs/bpp/BPP_Product.php';
		\BPP_Product::reset_test_models();

		$fallback = new ProjectTemplateFallback( new BuilderService() );
		$html     = $fallback->design_placeholder_page_html( 10 );

		$this->assertStringContainsString( 'pks-oi-design-placeholder', $html );
		$this->assertStringContainsString( '<p>', $html );
		$this->assertStringNotContainsString( 'pks-oi-design-placeholder__image', $html );
	}
}
