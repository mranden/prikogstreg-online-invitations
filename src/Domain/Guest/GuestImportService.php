<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Guest;

use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;

/**
 * Validates and imports guest CSV rows in bounded batches.
 */
final class GuestImportService {

	public const MAX_FILE_BYTES = 1_048_576;

	public const MAX_ROWS = 500;

	public const BATCH_SIZE = 50;

	public function __construct(
		private GuestRepository $guests,
		private GuestService $guests_service
	) {}

	/**
	 * @param array<string, mixed> $project
	 * @return array{success:bool,error?:string}|array{success:true,preview:array<string,mixed>}
	 */
	public function preview( array $project, string $csv ): array {
		if ( strlen( $csv ) > self::MAX_FILE_BYTES ) {
			return [ 'success' => false, 'error' => 'file_too_large' ];
		}

		$parsed = GuestCsv::parse_import( $csv );
		if ( isset( $parsed['error'] ) ) {
			return [ 'success' => false, 'error' => (string) $parsed['error'] ];
		}

		$rows = $parsed['rows'];
		if ( count( $rows ) > self::MAX_ROWS ) {
			return [ 'success' => false, 'error' => 'too_many_rows' ];
		}

		$preview_rows = [];
		foreach ( $rows as $index => $row ) {
			$warnings = [];
			$email    = (string) ( $row['email'] ?? '' );
			if ( '' !== $email && $this->guests->count_duplicate_email( (int) $project['project_id'], $email ) > 0 ) {
				$warnings[] = 'duplicate_email';
			}

			$preview_rows[] = [
				'line'     => $index + 2,
				'row'      => $row,
				'warnings' => $warnings,
			];
		}

		return [
			'success' => true,
			'preview' => [
				'total_rows' => count( $preview_rows ),
				'rows'       => $preview_rows,
				'batches'    => (int) ceil( count( $preview_rows ) / self::BATCH_SIZE ),
			],
		];
	}

	/**
	 * @param array<string, mixed> $project
	 * @param list<array<string, string>> $rows
	 * @return array{imported:int,failed:int,errors:list<string>}
	 */
	public function import_rows( array $project, array $rows ): array {
		$imported = 0;
		$failed   = 0;
		$errors   = [];

		foreach ( array_slice( $rows, 0, self::MAX_ROWS ) as $index => $row ) {
			$result = $this->guests_service->create( $project, $row );
			if ( empty( $result['success'] ) ) {
				++$failed;
				$errors[] = sprintf( 'Row %d: %s', $index + 1, (string) ( $result['error'] ?? 'failed' ) );
				continue;
			}
			++$imported;
		}

		return [
			'imported' => $imported,
			'failed'   => $failed,
			'errors'   => $errors,
		];
	}
}
