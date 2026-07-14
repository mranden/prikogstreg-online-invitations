<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Support;

/**
 * Minimal valid image bytes for upload tests.
 */
final class PhotoFixtures {

	public static function png_1x1(): string {
		$raw = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true );

		return is_string( $raw ) ? $raw : '';
	}

	public static function jpeg_1x1(): string {
		if ( function_exists( 'imagecreatetruecolor' ) ) {
			$image = imagecreatetruecolor( 1, 1 );
			ob_start();
			imagejpeg( $image, null, 90 );
			imagedestroy( $image );
			$bytes = (string) ob_get_clean();

			return '' !== $bytes ? $bytes : self::png_1x1();
		}

		return self::png_1x1();
	}

	public static function webp_1x1(): string {
		if ( function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagewebp' ) ) {
			$image = imagecreatetruecolor( 1, 1 );
			ob_start();
			imagewebp( $image, null, 80 );
			imagedestroy( $image );
			$bytes = (string) ob_get_clean();

			return '' !== $bytes ? $bytes : self::png_1x1();
		}

		return self::png_1x1();
	}

	public static function svg(): string {
		return '<svg xmlns="http://www.w3.org/2000/svg"><text>hi</text></svg>';
	}

	public static function fake_jpeg_header(): string {
		return "\xFF\xD8\xFF\xE0" . str_repeat( 'A', 200 );
	}

	/**
	 * @return array{name:string,tmp_name:string,size:int,error:int}
	 */
	public static function file_from_bytes( string $bytes, string $name = 'photo.png' ): array {
		$tmp = tempnam( sys_get_temp_dir(), 'pks-oi-photo-' );
		if ( false === $tmp ) {
			return [
				'name'     => $name,
				'tmp_name' => '',
				'size'     => 0,
				'error'    => UPLOAD_ERR_CANT_WRITE,
			];
		}

		file_put_contents( $tmp, $bytes );

		return [
			'name'     => $name,
			'tmp_name' => $tmp,
			'size'     => strlen( $bytes ),
			'error'    => UPLOAD_ERR_OK,
		];
	}
}
