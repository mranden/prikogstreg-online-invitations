<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Guest;

use PrikOgStreg\OnlineInvitations\Database\Repositories\EventRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;

/**
 * One-time flash storage for personal invitation links after create/restore/rotate.
 */
final class GuestLinkFlash {

	private const TTL_SECONDS = 600;

	public static function store( int $guest_id, int $user_id, string $url ): void {
		set_transient( self::key( $guest_id, $user_id ), $url, self::TTL_SECONDS );
	}

	public static function consume( int $guest_id, int $user_id ): string {
		$key = self::key( $guest_id, $user_id );
		$url = get_transient( $key );
		if ( false !== $url ) {
			delete_transient( $key );
		}

		return is_string( $url ) ? $url : '';
	}

	private static function key( int $guest_id, int $user_id ): string {
		return 'pks_oi_guest_link_' . $guest_id . '_' . $user_id;
	}
}
