<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Photo;

use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;

/**
 * Stores and verifies photo access codes (fotokode) as password hashes only.
 */
final class PhotoAccessCodeService {

	public const MIN_LENGTH = 4;

	public function __construct(
		private ProjectRepository $projects,
		private PhotoAccessRateLimiter $rate_limiter
	) {}

	/**
	 * @param array<string, mixed> $project
	 * @return array{success:bool,error?:string}
	 */
	public function set_code( array $project, string $code, string $confirm ): array {
		$code    = trim( $code );
		$confirm = trim( $confirm );

		if ( strlen( $code ) < self::MIN_LENGTH ) {
			return [ 'success' => false, 'error' => 'code_too_short' ];
		}

		if ( $code !== $confirm ) {
			return [ 'success' => false, 'error' => 'code_mismatch' ];
		}

		$project_id = (int) ( $project['project_id'] ?? 0 );
		$version    = max( 1, (int) ( $project['photo_access_code_version'] ?? 0 ) + 1 );

		$this->projects->update(
			$project_id,
			[
				'photo_access_code_hash'    => wp_hash_password( $code ),
				'photo_access_code_version' => $version,
			]
		);

		PhotoAccessCodeDisplayStore::remember( $project_id, $code );

		do_action( 'pks_oi_photo_access_code_changed', $project_id, $version );

		return [ 'success' => true ];
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array{success:bool,error?:string}
	 */
	public function verify( array $project, string $code, string $token_hash, string $client_key ): array {
		if ( ! $this->rate_limiter->allow( $token_hash, $client_key ) ) {
			return [ 'success' => false, 'error' => 'rate_limited' ];
		}

		$hash = (string) ( $project['photo_access_code_hash'] ?? '' );
		if ( '' === $hash ) {
			$this->rate_limiter->record_failure( $token_hash, $client_key );

			return [ 'success' => false, 'error' => 'invalid_code' ];
		}

		if ( ! wp_check_password( $code, $hash ) ) {
			$this->rate_limiter->record_failure( $token_hash, $client_key );

			return [ 'success' => false, 'error' => 'invalid_code' ];
		}

		$this->rate_limiter->reset( $token_hash, $client_key );

		return [ 'success' => true ];
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public function has_code( array $project ): bool {
		return '' !== (string) ( $project['photo_access_code_hash'] ?? '' );
	}
}
