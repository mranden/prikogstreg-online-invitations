<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Photo;

use PrikOgStreg\OnlineInvitations\Domain\Project\PublicEntitlement;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

/**
 * Entitlement checks for the dedicated photo-sharing landing page.
 */
final class PhotoShareEntitlement {

	/**
	 * @param array<string, mixed> $project
	 */
	public static function is_available( array $project ): bool {
		if ( empty( $project['guest_photos_enabled'] ) ) {
			return false;
		}

		if ( '' === (string) ( $project['photo_share_token_hash'] ?? '' ) ) {
			return false;
		}

		return PublicEntitlement::is_publicly_available( $project );
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public static function is_upload_open( array $project ): bool {
		if ( ! self::is_available( $project ) ) {
			return false;
		}

		if ( '' === (string) ( $project['photo_access_code_hash'] ?? '' ) ) {
			return false;
		}

		$closes = (string) ( $project['photo_upload_closes_at_utc'] ?? '' );
		if ( '' !== $closes ) {
			$timestamp = strtotime( $closes . ' UTC' );
			if ( false !== $timestamp && $timestamp <= time() ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public static function is_gallery_public( array $project ): bool {
		return self::is_available( $project ) && ! empty( $project['photo_gallery_public_enabled'] );
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public static function auto_approve_enabled( array $project ): bool {
		if ( ! array_key_exists( 'photo_auto_approve_enabled', $project ) ) {
			return true;
		}

		return ! empty( $project['photo_auto_approve_enabled'] );
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public static function is_share_ready( array $project ): bool {
		return self::is_available( $project )
			&& '' !== (string) ( $project['photo_access_code_hash'] ?? '' );
	}

	/**
	 * Owner My Account: share link + QR can be prepared before guests need public access.
	 *
	 * @param array<string, mixed> $project
	 */
	public static function is_owner_share_configured( array $project ): bool {
		if ( empty( $project['guest_photos_enabled'] ) ) {
			return false;
		}

		if ( '' === (string) ( $project['photo_share_token_hash'] ?? '' ) ) {
			return false;
		}

		return '' !== (string) ( $project['photo_access_code_hash'] ?? '' );
	}
}
