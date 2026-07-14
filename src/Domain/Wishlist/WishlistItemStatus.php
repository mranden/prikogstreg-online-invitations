<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Wishlist;

final class WishlistItemStatus {

	public const ACTIVE = 'active';

	public const HIDDEN = 'hidden';

	public const ARCHIVED = 'archived';

	/**
	 * @return list<string>
	 */
	public static function owner_visible(): array {
		return [ self::ACTIVE, self::HIDDEN ];
	}
}
