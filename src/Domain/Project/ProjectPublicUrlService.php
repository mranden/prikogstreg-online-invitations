<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

use PrikOgStreg\OnlineInvitations\Security\InvitationToken;

/**
 * Resolves the organiser-facing generic public invitation URL.
 */
final class ProjectPublicUrlService {

	public const META_KEY = '_pks_oi_generic_token_raw';

	public function __construct(
		private GenericTokenService $generic_tokens
	) {}

	public function store_raw_token( int $project_id, string $raw_token ): void {
		if ( $project_id <= 0 || '' === $raw_token ) {
			return;
		}

		update_post_meta( $project_id, self::META_KEY, $raw_token );
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public function resolve_url( array $project ): ?string {
		$project_id = (int) ( $project['project_id'] ?? 0 );
		$hash       = (string) ( $project['generic_token_hash'] ?? '' );

		if ( $project_id <= 0 || '' === $hash ) {
			return null;
		}

		$stored = get_post_meta( $project_id, self::META_KEY, true );
		if ( is_string( $stored ) && '' !== $stored && InvitationToken::hash( $stored ) === $hash ) {
			return InvitationToken::public_url( $stored );
		}

		$rotated = $this->generic_tokens->rotate( $project );
		$this->store_raw_token( $project_id, $rotated['token'] );

		return $rotated['url'];
	}
}
