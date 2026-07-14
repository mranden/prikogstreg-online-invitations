<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;

/**
 * Rotates and revokes generic project invitation links.
 */
final class GenericTokenService {

	public function __construct(
		private ProjectRepository $projects
	) {}

	/**
	 * @param array<string, mixed> $project
	 * @return array{token:string,url:string,version:int}
	 */
	public function rotate( array $project ): array {
		$pair    = InvitationToken::generate();
		$version = max( 1, (int) ( $project['generic_token_version'] ?? 1 ) + 1 );

		$this->projects->update(
			(int) $project['project_id'],
			[
				'generic_token_hash'    => $pair['hash'],
				'generic_token_version' => $version,
			]
		);

		update_post_meta( (int) $project['project_id'], ProjectPublicUrlService::META_KEY, $pair['raw'] );

		do_action( 'pks_oi_generic_token_rotated', (int) $project['project_id'], $version );

		return [
			'token'   => $pair['raw'],
			'url'     => InvitationToken::public_url( $pair['raw'] ),
			'version' => $version,
		];
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public function revoke( array $project ): void {
		$this->projects->update(
			(int) $project['project_id'],
			[
				'generic_token_hash'    => null,
				'generic_token_version' => max( 1, (int) ( $project['generic_token_version'] ?? 1 ) + 1 ),
			]
		);

		do_action( 'pks_oi_generic_token_revoked', (int) $project['project_id'] );
	}
}
