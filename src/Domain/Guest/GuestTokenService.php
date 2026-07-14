<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Guest;

use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

/**
 * Rotates and revokes personal guest invitation links.
 */
final class GuestTokenService {

	public function __construct(
		private GuestRepository $guests
	) {}

	/**
	 * @param array<string, mixed> $guest
	 * @return array{token:string,url:string,version:int}
	 */
	public function rotate( array $guest ): array {
		$pair    = InvitationToken::generate();
		$version = max( 1, (int) ( $guest['token_version'] ?? 1 ) + 1 );

		$this->guests->update(
			(int) $guest['guest_id'],
			[
				'token_hash'    => $pair['hash'],
				'token_version' => $version,
			]
		);

		do_action( 'pks_oi_guest_token_rotated', (int) $guest['guest_id'], (int) $guest['project_id'], $version );

		GuestSendTokenStore::remember( (int) $guest['guest_id'], $pair['raw'] );

		return [
			'token'   => $pair['raw'],
			'url'     => InvitationToken::public_url( $pair['raw'] ),
			'version' => $version,
		];
	}

	/**
	 * @param array<string, mixed> $guest
	 */
	public function revoke( array $guest ): void {
		$this->guests->update(
			(int) $guest['guest_id'],
			[
				'archived_at_utc'   => UtcDateTime::now(),
				'invitation_status' => 'cancelled',
			]
		);

		do_action( 'pks_oi_guest_token_revoked', (int) $guest['guest_id'], (int) $guest['project_id'] );
	}

	/**
	 * @param array<string, mixed> $guest
	 * @return array{token:string,url:string,version:int}
	 */
	public function restore_access( array $guest ): array {
		$pair    = InvitationToken::generate();
		$version = max( 1, (int) ( $guest['token_version'] ?? 1 ) + 1 );

		$this->guests->update(
			(int) $guest['guest_id'],
			[
				'archived_at_utc'   => null,
				'invitation_status' => InvitationStatus::NOT_SENT,
				'token_hash'        => $pair['hash'],
				'token_version'     => $version,
			]
		);

		do_action( 'pks_oi_guest_token_restored', (int) $guest['guest_id'], (int) $guest['project_id'], $version );

		GuestSendTokenStore::remember( (int) $guest['guest_id'], $pair['raw'] );

		return [
			'token'   => $pair['raw'],
			'url'     => InvitationToken::public_url( $pair['raw'] ),
			'version' => $version,
		];
	}
}
