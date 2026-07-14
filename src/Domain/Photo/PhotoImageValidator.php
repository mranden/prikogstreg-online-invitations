<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Photo;

/**
 * Content-based image validation before storage.
 */
final class PhotoImageValidator {

	/**
	 * @return array{success:bool,error?:string,mime?:string,width?:int,height?:int}
	 */
	public function validate_bytes( string $bytes ): array {
		if ( '' === $bytes ) {
			return [ 'success' => false, 'error' => 'empty_file' ];
		}

		if ( strlen( $bytes ) > PhotoLimits::MAX_FILE_BYTES ) {
			return [ 'success' => false, 'error' => 'file_too_large' ];
		}

		if ( $this->contains_dangerous_content( $bytes ) ) {
			return [ 'success' => false, 'error' => 'dangerous_content' ];
		}

		$mime = $this->detect_mime( $bytes );
		if ( null === $mime || ! in_array( $mime, PhotoLimits::ALLOWED_MIME_TYPES, true ) ) {
			return [ 'success' => false, 'error' => 'invalid_mime' ];
		}

		$dimensions = $this->read_dimensions( $bytes );
		if ( null === $dimensions ) {
			return [ 'success' => false, 'error' => 'invalid_image' ];
		}

		[ $width, $height ] = $dimensions;
		if ( $width * $height > PhotoLimits::MAX_PIXELS ) {
			return [ 'success' => false, 'error' => 'dimensions_too_large' ];
		}

		return [
			'success' => true,
			'mime'    => $mime,
			'width'   => $width,
			'height'  => $height,
		];
	}

	private function detect_mime( string $bytes ): ?string {
		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			if ( false !== $finfo ) {
				$mime = finfo_buffer( $finfo, $bytes );
				finfo_close( $finfo );
				if ( is_string( $mime ) && '' !== $mime ) {
					return $mime;
				}
			}
		}

		if ( str_starts_with( $bytes, "\xFF\xD8\xFF" ) ) {
			return 'image/jpeg';
		}
		if ( str_starts_with( $bytes, "\x89PNG\r\n\x1a\n" ) ) {
			return 'image/png';
		}
		if ( str_starts_with( $bytes, 'RIFF' ) && str_contains( substr( $bytes, 0, 16 ), 'WEBP' ) ) {
			return 'image/webp';
		}

		return null;
	}

	/**
	 * @return array{0:int,1:int}|null
	 */
	private function read_dimensions( string $bytes ): ?array {
		if ( function_exists( 'getimagesizefromstring' ) ) {
			$info = @getimagesizefromstring( $bytes );
			if ( is_array( $info ) && isset( $info[0], $info[1] ) ) {
				return [ (int) $info[0], (int) $info[1] ];
			}
		}

		return null;
	}

	private function contains_dangerous_content( string $bytes ): bool {
		$sample = substr( $bytes, 0, 4096 );

		return str_contains( $sample, '<?php' )
			|| str_contains( $sample, '<svg' )
			|| str_contains( $sample, '<script' );
	}
}
