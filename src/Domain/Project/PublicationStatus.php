<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

final class PublicationStatus {

	public const UNPUBLISHED = 'unpublished';
	public const PUBLISHED   = 'published';

	/**
	 * @return list<string>
	 */
	public static function all(): array {
		return [ self::UNPUBLISHED, self::PUBLISHED ];
	}
}
