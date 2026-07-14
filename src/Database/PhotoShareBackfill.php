<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Database;

use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoShareTokenService;

/**
 * Backfills photo share tokens for projects that had guest photos enabled before v3.
 */
final class PhotoShareBackfill {

	public function __construct(
		private ProjectRepository $projects,
		private PhotoShareTokenService $share_tokens
	) {}

	public function run(): void {
		$rows = $this->projects->list_with_photos_enabled_missing_share_token();
		foreach ( $rows as $project ) {
			$this->share_tokens->ensure_token( $project );
		}
	}
}
