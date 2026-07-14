<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

final class ProjectStatus {

	public const DRAFT     = 'draft';
	public const ACTIVE    = 'active';
	public const RESTRICTED = 'restricted';
	public const EXPIRED   = 'expired';
	public const ARCHIVED  = 'archived';
	public const DELETED   = 'deleted';

	/**
	 * @return list<string>
	 */
	public static function all(): array {
		return [
			self::DRAFT,
			self::ACTIVE,
			self::RESTRICTED,
			self::EXPIRED,
			self::ARCHIVED,
			self::DELETED,
		];
	}
}
