<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

/**
 * Resolves BPP poster canvas dimensions for responsive public viewport scaling.
 */
final class PosterDimensions {

	/** @var array<string, array{width:int,height:int}> */
	private const SIZE_MAP = [
		'a5'     => [ 'width' => 510, 'height' => 680 ],
		'a6'     => [ 'width' => 360, 'height' => 510 ],
		'a4'     => [ 'width' => 595, 'height' => 842 ],
		'square' => [ 'width' => 510, 'height' => 510 ],
		'dl'     => [ 'width' => 495, 'height' => 255 ],
	];

	public const DEFAULT_WIDTH  = 510;
	public const DEFAULT_HEIGHT = 680;

	/**
	 * @return array{width:int,height:int,orientation:string,size:string,format:string}
	 */
	public static function resolve( string $size, string $format, string $sample_html = '' ): array {
		$size   = sanitize_title( $size );
		$format = sanitize_title( $format );

		if ( '' === $size ) {
			$size = 'a5';
		}

		if ( '' === $format ) {
			$format = 'flat';
		}

		$from_html = self::parse_from_html( $sample_html );
		if ( null !== $from_html ) {
			$width  = $from_html['width'];
			$height = $from_html['height'];
		} else {
			$mapped = self::SIZE_MAP[ $size ] ?? self::SIZE_MAP['a5'];
			$width  = $mapped['width'];
			$height = $mapped['height'];
		}

		if ( 'landscape' === $format && $width < $height ) {
			[ $width, $height ] = [ $height, $width ];
		}

		$orientation = $width >= $height ? 'landscape' : 'portrait';

		return [
			'width'       => max( 1, $width ),
			'height'      => max( 1, $height ),
			'orientation' => $orientation,
			'size'        => $size,
			'format'      => $format,
		];
	}

	/**
	 * @return array{width:int,height:int}|null
	 */
	private static function parse_from_html( string $html ): ?array {
		if ( '' === $html ) {
			return null;
		}

		if ( preg_match( '/width:\s*(\d+(?:\.\d+)?)px/i', $html, $width_match ) !== 1 ) {
			return null;
		}

		if ( preg_match( '/height:\s*(\d+(?:\.\d+)?)px/i', $html, $height_match ) !== 1 ) {
			return null;
		}

		$width  = (int) round( (float) $width_match[1] );
		$height = (int) round( (float) $height_match[1] );

		if ( $width < 1 || $height < 1 ) {
			return null;
		}

		return [
			'width'  => $width,
			'height' => $height,
		];
	}
}
