<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\Domain\Photo;

use Endroid\QrCode\Writer\SvgWriter;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoShareQrService;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class PhotoShareQrServiceTest extends TestCase {

	public function test_svg_for_url_returns_svg_markup(): void {
		if ( ! class_exists( SvgWriter::class ) ) {
			$this->markTestSkipped( 'endroid/qr-code is not installed.' );
		}

		$svg = ( new PhotoShareQrService() )->svg_for_url( 'https://example.test/photos/token/' );

		$this->assertStringContainsString( '<svg', $svg );
	}

	public function test_svg_for_empty_url_returns_empty_string(): void {
		$this->assertSame( '', ( new PhotoShareQrService() )->svg_for_url( '' ) );
	}
}
