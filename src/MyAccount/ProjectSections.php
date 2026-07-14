<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\MyAccount;

/**
 * Allowlisted My Account project sections.
 */
final class ProjectSections {

	public const OVERVIEW      = 'overview';
	public const DESIGN        = 'design';
	public const EVENT         = 'event';
	public const GUESTS        = 'guests';
	public const ADDRESS_BOOK  = 'address-book';
	public const PREVIEW       = 'preview';
	public const PUBLISH       = 'publish';
	public const RESPONSES     = 'responses';
	public const WISHLIST      = 'wishlist';
	public const PHOTOS        = 'photos';
	public const SETTINGS      = 'settings';

	/**
	 * @return list<string>
	 */
	public static function all(): array {
		return [
			self::OVERVIEW,
			self::DESIGN,
			self::EVENT,
			self::GUESTS,
			self::ADDRESS_BOOK,
			self::PREVIEW,
			self::PUBLISH,
			self::RESPONSES,
			self::WISHLIST,
			self::PHOTOS,
			self::SETTINGS,
		];
	}

	public static function is_valid( string $section ): bool {
		return in_array( $section, self::all(), true );
	}

	/**
	 * Sections kept in the backend but hidden from customer navigation.
	 *
	 * @return list<string>
	 */
	public static function hidden(): array {
		return [
			self::ADDRESS_BOOK,
		];
	}

	public static function is_visible( string $section ): bool {
		return self::is_valid( $section ) && ! in_array( $section, self::hidden(), true );
	}

	public static function default_section(): string {
		return self::OVERVIEW;
	}

	/**
	 * @return array<string, string>
	 */
	public static function visible_labels(): array {
		$labels = self::labels();

		foreach ( self::hidden() as $hidden ) {
			unset( $labels[ $hidden ] );
		}

		return $labels;
	}

	/**
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return [
			self::OVERVIEW     => __( 'Overview', 'prikogstreg-online-invitations' ),
			self::DESIGN       => __( 'Design', 'prikogstreg-online-invitations' ),
			self::EVENT        => __( 'Event', 'prikogstreg-online-invitations' ),
			self::GUESTS       => __( 'Guests', 'prikogstreg-online-invitations' ),
			self::ADDRESS_BOOK => __( 'Address book', 'prikogstreg-online-invitations' ),
			self::PREVIEW      => __( 'Preview', 'prikogstreg-online-invitations' ),
			self::PUBLISH      => __( 'Publish', 'prikogstreg-online-invitations' ),
			self::RESPONSES    => __( 'Responses', 'prikogstreg-online-invitations' ),
			self::WISHLIST     => __( 'Wishlist', 'prikogstreg-online-invitations' ),
			self::PHOTOS       => __( 'Photos', 'prikogstreg-online-invitations' ),
			self::SETTINGS     => __( 'Settings', 'prikogstreg-online-invitations' ),
		];
	}

	public static function is_implemented( string $section ): bool {
		return in_array( $section, self::implemented(), true );
	}

	/**
	 * @return list<string>
	 */
	public static function implemented(): array {
		return [
			self::OVERVIEW,
			self::DESIGN,
			self::EVENT,
			self::PREVIEW,
			self::PUBLISH,
			self::GUESTS,
			self::RESPONSES,
			self::WISHLIST,
			self::PHOTOS,
			self::SETTINGS,
		];
	}
}
