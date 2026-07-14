<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Photo;

/**
 * Re-encodes images to strip EXIF/metadata when GD is available.
 */
final class PhotoImageProcessor {

	/**
	 * @return array{success:bool,error?:string,bytes?:string,mime?:string,width?:int,height?:int}
	 */
	public function process( string $bytes, string $mime ): array {
		if ( ! function_exists( 'imagecreatefromstring' ) ) {
			return [
				'success' => true,
				'bytes'   => $bytes,
				'mime'    => $mime,
			];
		}

		$image = @imagecreatefromstring( $bytes );
		if ( false === $image ) {
			return [ 'success' => false, 'error' => 'decode_failed' ];
		}

		$width  = imagesx( $image );
		$height = imagesy( $image );
		ob_start();
		$ok = match ( $mime ) {
			'image/png'  => imagepng( $image, null, 6 ),
			'image/webp' => function_exists( 'imagewebp' ) ? imagewebp( $image, null, 85 ) : imagejpeg( $image, null, 85 ),
			default      => imagejpeg( $image, null, 85 ),
		};
		imagedestroy( $image );
		$output = (string) ob_get_clean();

		if ( ! $ok || '' === $output ) {
			return [ 'success' => false, 'error' => 'encode_failed' ];
		}

		$out_mime = 'image/jpeg';
		if ( 'image/png' === $mime ) {
			$out_mime = 'image/png';
		} elseif ( 'image/webp' === $mime && function_exists( 'imagewebp' ) ) {
			$out_mime = 'image/webp';
		}

		return [
			'success' => true,
			'bytes'   => $output,
			'mime'    => $out_mime,
			'width'   => $width,
			'height'  => $height,
		];
	}

	/**
	 * @return array{success:bool,error?:string,bytes?:string,relative_path?:string}
	 */
	public function thumbnail( string $bytes, string $storage_uuid, string $file_uuid ): array {
		if ( ! function_exists( 'imagecreatefromstring' ) ) {
			return [ 'success' => false, 'error' => 'thumbnail_unavailable' ];
		}

		$image = @imagecreatefromstring( $bytes );
		if ( false === $image ) {
			return [ 'success' => false, 'error' => 'decode_failed' ];
		}

		$width  = imagesx( $image );
		$height = imagesy( $image );
		$max    = 320;
		$scale  = min( 1, $max / max( $width, $height ) );
		$tw     = max( 1, (int) round( $width * $scale ) );
		$th     = max( 1, (int) round( $height * $scale ) );
		$thumb  = imagecreatetruecolor( $tw, $th );
		imagecopyresampled( $thumb, $image, 0, 0, 0, 0, $tw, $th, $width, $height );
		imagedestroy( $image );

		ob_start();
		$ok = imagejpeg( $thumb, null, 80 );
		imagedestroy( $thumb );
		$output = (string) ob_get_clean();
		if ( ! $ok || '' === $output ) {
			return [ 'success' => false, 'error' => 'encode_failed' ];
		}

		return [
			'success'       => true,
			'bytes'         => $output,
			'relative_path' => 'photos/thumbnails/' . $file_uuid . '.jpg',
		];
	}
}
