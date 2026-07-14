<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\Domain\Photo;

use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoImageValidator;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoLimits;
use PrikOgStreg\OnlineInvitations\Tests\Support\PhotoFixtures;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class PhotoImageValidatorTest extends TestCase {

	public function test_dimensions_too_large_rejected(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagejpeg' ) ) {
			$this->markTestSkipped( 'GD extension required for dimension fixture generation.' );
		}

		$side   = (int) floor( sqrt( PhotoLimits::MAX_PIXELS ) ) + 1;
		$image  = imagecreatetruecolor( $side, $side );
		$this->assertNotFalse( $image );

		ob_start();
		imagejpeg( $image, null, 20 );
		imagedestroy( $image );
		$bytes = (string) ob_get_clean();

		if ( '' === $bytes || strlen( $bytes ) > PhotoLimits::MAX_FILE_BYTES ) {
			$this->markTestSkipped( 'Could not generate dimension fixture within byte limits.' );
		}

		$result = ( new PhotoImageValidator() )->validate_bytes( $bytes );
		$this->assertFalse( $result['success'] ?? true );
		$this->assertSame( 'dimensions_too_large', $result['error'] ?? '' );
	}

	public function test_valid_small_image_accepted(): void {
		$result = ( new PhotoImageValidator() )->validate_bytes( PhotoFixtures::png_1x1() );
		$this->assertTrue( $result['success'] );
	}
}
