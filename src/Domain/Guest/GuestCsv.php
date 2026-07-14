<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Guest;

/**
 * CSV export/import helpers with spreadsheet injection protection.
 */
final class GuestCsv {

	public const EXPORT_COLUMNS = [
		'display_name',
		'email',
		'phone',
		'party_label',
		'attendee_count',
		'rsvp_status',
		'invitation_status',
		'responded_at',
	];

	public const IMPORT_COLUMNS = [
		'display_name',
		'email',
		'phone',
		'party_label',
		'attendee_count',
	];

	public static function neutralize_cell( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		$first = $value[0];
		if ( in_array( $first, [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
			return "'" . $value;
		}

		return $value;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, string>
	 */
	public static function neutralize_row( array $row ): array {
		$mapped = $row;
		$mapped['responded_at'] = (string) ( $row['responded_at_utc'] ?? $row['responded_at'] ?? '' );

		$neutralized = [];
		foreach ( self::EXPORT_COLUMNS as $column ) {
			$neutralized[ $column ] = self::neutralize_cell( (string) ( $mapped[ $column ] ?? '' ) );
		}

		return $neutralized;
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 */
	public static function build_export( array $rows ): string {
		$lines   = [];
		$lines[] = implode( ',', self::EXPORT_COLUMNS );

		foreach ( $rows as $row ) {
			$neutralized = self::neutralize_row( $row );
			$cells       = [];
			foreach ( self::EXPORT_COLUMNS as $column ) {
				$cells[] = self::escape_csv_cell( $neutralized[ $column ] );
			}
			$lines[] = implode( ',', $cells );
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * @return array{headers:list<string>,rows:list<array<string,string>>}|array{error:string}
	 */
	public static function parse_import( string $csv ): array {
		$csv = trim( $csv );
		if ( '' === $csv ) {
			return [ 'error' => 'empty_file' ];
		}

		$lines = preg_split( '/\r\n|\r|\n/', $csv ) ?: [];
		if ( [] === $lines ) {
			return [ 'error' => 'empty_file' ];
		}

		$headers = str_getcsv( (string) array_shift( $lines ) );
		$headers = array_map( static fn( string $header ): string => strtolower( trim( $header ) ), $headers );
		if ( ! in_array( 'display_name', $headers, true ) ) {
			return [ 'error' => 'missing_display_name_column' ];
		}

		$rows = [];
		foreach ( $lines as $line_number => $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}

			$cells = str_getcsv( $line );
			$assoc = [];
			foreach ( $headers as $index => $header ) {
				if ( ! in_array( $header, self::IMPORT_COLUMNS, true ) ) {
					continue;
				}
				$assoc[ $header ] = self::sanitize_import_cell( $header, (string) ( $cells[ $index ] ?? '' ) );
			}

			if ( '' === trim( (string) ( $assoc['display_name'] ?? '' ) ) ) {
				return [ 'error' => 'missing_display_name', 'line' => $line_number + 2 ];
			}

			$rows[] = $assoc;
		}

		return [
			'headers' => $headers,
			'rows'    => $rows,
		];
	}

	private static function sanitize_import_cell( string $column, string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		$value = self::strip_formula_prefix( $value );

		if ( 'email' === $column ) {
			$email = sanitize_email( $value );

			return '' !== $email ? $email : '';
		}

		if ( 'attendee_count' === $column ) {
			return (string) max( 0, (int) $value );
		}

		return sanitize_text_field( $value );
	}

	private static function strip_formula_prefix( string $value ): string {
		while ( '' !== $value && in_array( $value[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
			$value = ltrim( substr( $value, 1 ) );
		}

		return $value;
	}

	private static function escape_csv_cell( string $value ): string {
		if ( str_contains( $value, '"' ) || str_contains( $value, ',' ) || str_contains( $value, "\n" ) ) {
			return '"' . str_replace( '"', '""', $value ) . '"';
		}

		return $value;
	}
}
