<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\Support;

use PrikOgStreg\OnlineInvitations\Support\PublishedHtmlValidator;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class PublishedHtmlValidatorTest extends TestCase {

	public function test_detects_visible_text_content(): void {
		$this->assertTrue(
			PublishedHtmlValidator::has_visible_content( '<div><p>Hello guest</p></div>' )
		);
	}

	public function test_rejects_empty_wrapper(): void {
		$html = '<div class="bpp-public-invitation" data-bpp-schema-version="1"></div>';
		$this->assertFalse( PublishedHtmlValidator::has_visible_content( $html ) );
		$this->assertTrue( PublishedHtmlValidator::is_empty_wrapper_only( $html ) );
	}

	public function test_accepts_image_only_content(): void {
		$this->assertTrue(
			PublishedHtmlValidator::has_visible_content( '<img src="https://example.test/a.jpg" alt="">' )
		);
	}
}
