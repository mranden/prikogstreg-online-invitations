<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicEntitlement;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;

/**
 * Resolves opaque invitation tokens without leaking match type on failure.
 */
final class TokenResolver {

	public function __construct(
		private GuestRepository $guests,
		private ProjectRepository $projects
	) {}

	public function resolve( string $raw_token ): ?TokenResolution {
		if ( ! InvitationToken::is_valid_format( $raw_token ) ) {
			return null;
		}

		$hash = InvitationToken::hash( $raw_token );

		$guest = $this->guests->find_by_token_hash( $hash );
		if ( is_array( $guest ) ) {
			$project = $this->projects->find_by_id( (int) ( $guest['project_id'] ?? 0 ) );
			if ( ! is_array( $project ) ) {
				return null;
			}

			if ( ! PublicEntitlement::is_guest_accessible( $guest ) ) {
				return null;
			}

			return new TokenResolution( TokenResolution::TYPE_PERSONAL, $project, $guest );
		}

		$project = $this->projects->find_by_generic_token_hash( $hash );
		if ( ! is_array( $project ) ) {
			return null;
		}

		return new TokenResolution( TokenResolution::TYPE_GENERIC, $project, null );
	}
}
