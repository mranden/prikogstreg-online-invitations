<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\Security;

use PrikOgStreg\OnlineInvitations\Security\PublishedHtmlSanitizer;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class PublishedHtmlSanitizerTest extends TestCase {

	public function test_malicious_fixture_file_blocks_script_vectors(): void {
		$html = (string) file_get_contents( PKS_OI_PLUGIN_PATH . 'tests/Fixtures/malicious-page.html' );
		$this->assertTrue( PublishedHtmlSanitizer::contains_blocked_markup( $html ) );

		$this->expectException( \InvalidArgumentException::class );
		PublishedHtmlSanitizer::sanitize( $html );
	}

	/**
	 * @dataProvider blocked_markup_provider
	 */
	public function test_blocked_vectors_throw( string $html ): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'published_html_unsafe' );
		PublishedHtmlSanitizer::sanitize( $html );
	}

	/**
	 * @return list<array{0:string}>
	 */
	public static function blocked_markup_provider(): array {
		return [
			[ '<section><script>alert(1)</script></section>' ],
			[ '<a href="javascript:alert(1)">x</a>' ],
			[ '<img src="x" onerror="alert(1)">' ],
			[ '<div class="customizer-page-content"><iframe src="https://evil.test"></iframe></div>' ],
			[ '<div style="width:expression(alert(1))">x</div>' ],
			[ '<style>@import url("https://evil.test/x.css");</style><div>x</div>' ],
			[ '<form action="/evil"><input type="text"></form>' ],
		];
	}

	public function test_legitimate_builder_markup_passes(): void {
		$html = '<div class="customizer-page-content"><span>Anna &amp; Bo</span></div>';
		$this->assertSame( $html, PublishedHtmlSanitizer::sanitize( $html ) );
	}

	/**
	 * @dataProvider oi_fixture_provider
	 */
	public function test_fixture_expectations( string $id, string $input, bool $blocked ): void {
		if ( $blocked ) {
			$this->assertTrue( PublishedHtmlSanitizer::contains_blocked_markup( $input ), $id );
			$this->expectException( \InvalidArgumentException::class );
			PublishedHtmlSanitizer::sanitize( $input );
		} else {
			$this->assertFalse( PublishedHtmlSanitizer::contains_blocked_markup( $input ), $id );
			$this->assertSame( $input, PublishedHtmlSanitizer::sanitize( $input ) );
		}
	}

	/**
	 * @return list<array{0:string,1:string,2:bool}>
	 */
	public static function oi_fixture_provider(): array {
		$path = PKS_OI_PLUGIN_PATH . 'tests/Fixtures/public-html-sanitizer-fixtures.json';
		if ( ! is_readable( $path ) ) {
			return [];
		}

		$raw  = file_get_contents( $path );
		$data = is_string( $raw ) ? json_decode( $raw, true ) : null;
		if ( ! is_array( $data ) || ! isset( $data['fixtures'] ) || ! is_array( $data['fixtures'] ) ) {
			return [];
		}

		$blocked_by_oi = [
			'script-tag',
			'onerror-attribute',
			'javascript-url',
			'malicious-style-expression',
			'malicious-style-import',
			'external-iframe',
		];
		$cases         = [];

		foreach ( $data['fixtures'] as $fixture ) {
			if ( ! is_array( $fixture ) || ! isset( $fixture['id'], $fixture['input'] ) ) {
				continue;
			}

			$cases[] = [
				(string) $fixture['id'],
				(string) $fixture['input'],
				in_array( (string) $fixture['id'], $blocked_by_oi, true ),
			];
		}

		return $cases;
	}
}
