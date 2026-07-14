<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Photo;

/**
 * V1 guest photo upload limits.
 */
final class PhotoLimits {

	public const MAX_FILE_BYTES = 10_485_760; // 10 MB

	public const MAX_PIXELS = 25_000_000;

	public const MAX_FILES_PER_REQUEST = 10;

	public const INTENT_TTL_SECONDS = 900;

	public const INTENT_RATE_MAX = 5;

	public const INTENT_RATE_WINDOW = 60;

	public const PROJECT_SOFT_LIMIT_BYTES = 536_870_912; // 512 MB

	/** @var list<string> */
	public const ALLOWED_MIME_TYPES = [
		'image/jpeg',
		'image/png',
		'image/webp',
	];
}
