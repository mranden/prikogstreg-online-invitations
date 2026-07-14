<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;

/**
 * Builds a normalized envelope configuration snapshot from a project row.
 */
final class EnvelopeSnapshot {

	/**
	 * @param array<string, mixed> $project
	 * @return array<string, mixed>
	 */
	public static function from_project_row( array $project ): array {
		$preset = ProductMeta::is_envelope_preset_valid( (string) ( $project['envelope_preset'] ?? '' ) )
			? (string) $project['envelope_preset']
			: 'classic';

		$background = ProductMeta::is_background_preset_valid( (string) ( $project['background_preset'] ?? '' ) )
			? (string) $project['background_preset']
			: 'neutral';

		return [
			'project_id'         => (int) ( $project['project_id'] ?? 0 ),
			'storage_uuid'       => (string) ( $project['storage_uuid'] ?? '' ),
			'source_product_id'  => (int) ( $project['product_id'] ?? 0 ),
			'preset'             => $preset,
			'background_preset'  => $background,
			'attachment_id'      => max( 0, (int) ( $project['envelope_image_id'] ?? 0 ) ),
			'media_storage'      => 'none',
			'snapshotted_at_utc' => UtcDateTime::now(),
		];
	}
}
