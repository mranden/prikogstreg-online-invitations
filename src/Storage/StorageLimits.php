<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Storage;

/**
 * Size and validation limits for project file storage.
 */
final class StorageLimits {

	public const MAX_STATE_BYTES = 16_777_216; // 16 MiB
	public const MAX_PAGE_BYTES  = 5_242_880;  // 5 MiB per page file
	public const MAX_MANIFEST_BYTES = 131_072; // 128 KiB
	public const TEMP_MAX_AGE_SECONDS = 3600;
}
