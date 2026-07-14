<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

/**
 * Tracks whether project design came from the customer order or a template fallback.
 */
final class ProjectDesignSource {

	public const CUSTOMER           = 'customer';
	public const TEMPLATE_FALLBACK  = 'template_fallback';

	/**
	 * @param array<string, mixed> $state
	 */
	public static function is_template_fallback( array $state ): bool {
		return self::TEMPLATE_FALLBACK === (string) ( $state['design_source'] ?? '' );
	}

	/**
	 * @param array<string, mixed> $state
	 */
	public static function mark_template_fallback( array $state ): array {
		$state['design_source'] = self::TEMPLATE_FALLBACK;

		if ( ! is_array( $state['field'] ?? null ) ) {
			$state['field'] = [];
		}

		return $state;
	}
}
