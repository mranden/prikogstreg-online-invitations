<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Photo;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Generates SVG QR codes for photo share URLs.
 */
final class PhotoShareQrService {

	public function svg_for_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}

		$qr_code = new QrCode(
			data: $url,
			encoding: new Encoding( 'UTF-8' ),
			errorCorrectionLevel: ErrorCorrectionLevel::High,
			size: 280,
			margin: 10,
		);

		$result = ( new SvgWriter() )->write( $qr_code );

		return $result->getString();
	}
}
