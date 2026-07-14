<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

/**
 * Builds structured event details for the public invitation page.
 */
final class PublicEventDetails {

	/**
	 * @param array<string, mixed> $project
	 * @return array<string, mixed>
	 */
	public static function from_project( array $project ): array {
		$timezone = (string) ( $project['timezone'] ?? 'Europe/Copenhagen' );
		$zone     = self::safe_timezone( $timezone );

		$date_label    = self::format_date_range( $project, $zone );
		$time_label    = self::format_time_range( $project, $zone );
		$venue_name    = trim( (string) ( $project['venue_name'] ?? '' ) );
		$address_lines = self::address_lines( $project );
		$practical     = trim( (string) ( $project['practical_info'] ?? '' ) );

		return [
			'has_content'    => '' !== $date_label
				|| '' !== $time_label
				|| '' !== $venue_name
				|| [] !== $address_lines
				|| '' !== $practical,
			'event_title'    => trim( (string) ( $project['event_title'] ?? '' ) ),
			'date_label'     => $date_label,
			'time_label'     => $time_label,
			'venue_name'     => $venue_name,
			'address_lines'  => $address_lines,
			'maps_url'       => self::maps_url( $project ),
			'practical_info' => $practical,
		];
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public static function has_displayable_content( array $project ): bool {
		return ! empty( self::from_project( $project )['has_content'] );
	}

	/**
	 * @param array<string, mixed> $project
	 * @return list<string>
	 */
	private static function address_lines( array $project ): array {
		$lines = [];

		foreach ( [ 'venue_address_line1', 'venue_address_line2' ] as $key ) {
			$value = trim( (string) ( $project[ $key ] ?? '' ) );
			if ( '' !== $value ) {
				$lines[] = $value;
			}
		}

		$city_line = trim(
			implode(
				' ',
				array_filter(
					[
						trim( (string) ( $project['venue_postcode'] ?? '' ) ),
						trim( (string) ( $project['venue_city'] ?? '' ) ),
					]
				)
			)
		);
		if ( '' !== $city_line ) {
			$lines[] = $city_line;
		}

		$country = trim( (string) ( $project['venue_country'] ?? '' ) );
		if ( '' !== $country ) {
			$lines[] = $country;
		}

		return $lines;
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private static function maps_url( array $project ): string {
		$query = array_filter(
			[
				trim( (string) ( $project['venue_name'] ?? '' ) ),
				trim( (string) ( $project['venue_address_line1'] ?? '' ) ),
				trim( (string) ( $project['venue_postcode'] ?? '' ) ),
				trim( (string) ( $project['venue_city'] ?? '' ) ),
			]
		);

		if ( [] === $query ) {
			return '';
		}

		return 'https://maps.google.com/maps?q=' . rawurlencode( implode( ', ', $query ) );
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private static function format_date_range( array $project, \DateTimeZone $zone ): string {
		$start = self::parse_utc( (string) ( $project['event_start_utc'] ?? '' ), $zone );
		$end   = self::parse_utc( (string) ( $project['event_end_utc'] ?? '' ), $zone );

		if ( null === $start && null === $end ) {
			return '';
		}

		if ( null !== $start && null !== $end ) {
			if ( $start->format( 'Y-m-d' ) === $end->format( 'Y-m-d' ) ) {
				return wp_date( 'j. F Y', $start->getTimestamp(), $zone );
			}

			return wp_date( 'j. F Y', $start->getTimestamp(), $zone )
				. ' – '
				. wp_date( 'j. F Y', $end->getTimestamp(), $zone );
		}

		$single = $start ?? $end;

		return null !== $single ? wp_date( 'j. F Y', $single->getTimestamp(), $zone ) : '';
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private static function format_time_range( array $project, \DateTimeZone $zone ): string {
		$start = self::parse_utc( (string) ( $project['event_start_utc'] ?? '' ), $zone );
		$end   = self::parse_utc( (string) ( $project['event_end_utc'] ?? '' ), $zone );

		if ( null === $start && null === $end ) {
			return '';
		}

		if ( null !== $start && null !== $end ) {
			return wp_date( 'H:i', $start->getTimestamp(), $zone )
				. ' – '
				. wp_date( 'H:i', $end->getTimestamp(), $zone );
		}

		$single = $start ?? $end;

		return null !== $single ? wp_date( 'H:i', $single->getTimestamp(), $zone ) : '';
	}

	private static function parse_utc( string $utc, \DateTimeZone $zone ): ?\DateTimeImmutable {
		$utc = trim( $utc );
		if ( '' === $utc ) {
			return null;
		}

		try {
			return ( new \DateTimeImmutable( $utc, new \DateTimeZone( 'UTC' ) ) )->setTimezone( $zone );
		} catch ( \Exception ) {
			return null;
		}
	}

	private static function safe_timezone( string $timezone ): \DateTimeZone {
		try {
			return new \DateTimeZone( '' !== $timezone ? $timezone : 'Europe/Copenhagen' );
		} catch ( \Exception ) {
			return new \DateTimeZone( 'Europe/Copenhagen' );
		}
	}
}
