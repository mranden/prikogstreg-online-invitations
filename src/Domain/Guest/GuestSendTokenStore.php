<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Guest;

use PrikOgStreg\OnlineInvitations\Security\InvitationToken;

/**
 * Short-lived storage of raw guest tokens for background e-mail delivery.
 *
 * Only token hashes are persisted in the database. This transient lets scheduled
 * sends build personal URLs without logging raw tokens.
 */
final class GuestSendTokenStore {

	private const TRANSIENT_PREFIX = 'pks_oi_guest_send_token_';

	private const TTL_SECONDS = 7776000; // 90 days

	public static function remember( int $guest_id, string $raw_token ): void {
		if ( $guest_id <= 0 || '' === $raw_token ) {
			return;
		}

		set_transient( self::key( $guest_id ), $raw_token, self::TTL_SECONDS );
	}

	public static function forget( int $guest_id ): void {
		delete_transient( self::key( $guest_id ) );
	}

	public static function invitation_url( int $guest_id ): string {
		$raw = get_transient( self::key( $guest_id ) );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return '';
		}

		return InvitationToken::public_url( $raw );
	}

	private static function key( int $guest_id ): string {
		return self::TRANSIENT_PREFIX . $guest_id;
	}
}
