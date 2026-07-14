<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use PrikOgStreg\OnlineInvitations\Support\BuilderPageHtmlNormalizer;

final class BuilderPageHtmlNormalizerTest extends TestCase {

	public function test_unescapes_json_encoded_attribute_quotes(): void {
		$raw      = '<div data-uuid=\"abc\" class=\"bpp-drag-element\">Hi</div>';
		$expected = '<div data-uuid="abc" class="bpp-drag-element">Hi</div>';

		$this->assertSame( $expected, BuilderPageHtmlNormalizer::normalize( $raw ) );
	}

	public function test_restores_full_size_image_urls(): void {
		$raw = '<img src=\"https://example.test/image-719x1024.png\">';

		$this->assertSame(
			'<img src="https://example.test/image.png">',
			BuilderPageHtmlNormalizer::normalize( $raw )
		);
	}
}
