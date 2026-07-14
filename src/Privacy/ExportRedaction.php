<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Privacy;

/**
 * Removes sensitive fields from export payloads.
 */
final class ExportRedaction {

	/** @var list<string> */
	private const PROJECT_SENSITIVE = [
		'generic_token_hash',
		'generic_token_version',
		'state_manifest_path',
		'published_manifest_path',
		'last_error_code',
	];

	/** @var list<string> */
	private const GUEST_SENSITIVE = [
		'token_hash',
		'token_version',
	];

	/**
	 * @param array<string, mixed> $row
	 * @return list<array{name:string,value:string}>
	 */
	public static function project_fields( array $row ): array {
		return self::scalar_fields( $row, self::PROJECT_SENSITIVE );
	}

	/**
	 * @param array<string, mixed> $row
	 * @return list<array{name:string,value:string}>
	 */
	public static function guest_fields( array $row ): array {
		return self::scalar_fields( $row, self::GUEST_SENSITIVE );
	}

	/**
	 * @param array<string, mixed> $row
	 * @return list<array{name:string,value:string}>
	 */
	public static function address_book_fields( array $row ): array {
		return self::scalar_fields( $row, [ 'normalized_email_hash' ] );
	}

	/**
	 * @param array<string, mixed> $row
	 * @return list<array{name:string,value:string}>
	 */
	public static function photo_fields( array $row ): array {
		return self::scalar_fields( $row, [ 'sha256' ] );
	}

	/**
	 * @param array<string, mixed> $row
	 * @return list<array{name:string,value:string}>
	 */
	public static function delivery_fields( array $row ): array {
		return self::scalar_fields( $row, [ 'last_error_message' ] );
	}

	/**
	 * @param array<string, mixed> $row
	 * @return list<array{name:string,value:string}>
	 */
	public static function event_fields( array $row ): array {
		$fields = self::scalar_fields( $row, [] );
		$metadata = (string) ( $row['metadata_json'] ?? '{}' );
		$fields[] = [
			'name'  => 'metadata_json',
			'value' => $metadata,
		];

		return $fields;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return list<array{name:string,value:string}>
	 */
	public static function scalar_fields_public( array $row ): array {
		return self::scalar_fields( $row, [] );
	}

	/**
	 * @param array<string, mixed> $row
	 * @param list<string>         $exclude
	 * @return list<array{name:string,value:string}>
	 */
	private static function scalar_fields( array $row, array $exclude ): array {
		$fields = [];
		foreach ( $row as $key => $value ) {
			if ( ! is_string( $key ) || in_array( $key, $exclude, true ) ) {
				continue;
			}
			if ( ! is_scalar( $value ) && null !== $value ) {
				continue;
			}
			$fields[] = [
				'name'  => $key,
				'value' => null === $value ? '' : (string) $value,
			];
		}

		return $fields;
	}
}
