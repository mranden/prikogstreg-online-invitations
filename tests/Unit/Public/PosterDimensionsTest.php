<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\Public;

use PrikOgStreg\OnlineInvitations\Public\PosterDimensions;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class PosterDimensionsTest extends TestCase {

	public function test_default_a5_portrait_dimensions(): void {
		$dimensions = PosterDimensions::resolve( 'a5', 'flat' );
		$this->assertSame( 510, $dimensions['width'] );
		$this->assertSame( 680, $dimensions['height'] );
		$this->assertSame( 'portrait', $dimensions['orientation'] );
	}

	public function test_landscape_format_swaps_dimensions(): void {
		$dimensions = PosterDimensions::resolve( 'a5', 'landscape' );
		$this->assertSame( 680, $dimensions['width'] );
		$this->assertSame( 510, $dimensions['height'] );
		$this->assertSame( 'landscape', $dimensions['orientation'] );
	}
}
