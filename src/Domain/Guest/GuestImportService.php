<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Guest;

use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Domain\AddressBook\AddressBookService;

/**
 * Validates and imports guest CSV rows in bounded batches.
 */
final class GuestImportService {

	public const MAX_FILE_BYTES = 1_048_576;

	public const MAX_ROWS = 500;

	public const BATCH_SIZE = 50;

	public function __construct(
		private GuestRepository $guests,
		private GuestService $guests_service,
		private AddressBookService $address_book
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

			$this->sync_guest_to_address_book( $project, (int) ( $result['guest_id'] ?? 0 ) );
			++$imported;
		}

		return [
			'imported' => $imported,
			'failed'   => $failed,
			'errors'   => $errors,
		];
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function sync_guest_to_address_book( array $project, int $guest_id ): void {
		if ( $guest_id <= 0 ) {
			return;
		}

		$guest = $this->guests->find_by_id_for_project( $guest_id, (int) ( $project['project_id'] ?? 0 ) );
		if ( ! is_array( $guest ) ) {
			return;
		}

		$this->address_book->save_guest_snapshot( (int) ( $project['user_id'] ?? 0 ), $guest );
	}
}
