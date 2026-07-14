<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

use PrikOgStreg\OnlineInvitations\Database\Repositories\EventRepository;

/**
 * Safe audit events for admin lifecycle actions.
 */
final class ProjectLifecycleAudit {

	public function __construct(
		private EventRepository $events
	) {}

	/**
	 * @param array<string, mixed> $metadata
	 */
	public function record_admin( int $project_id, string $event_type, array $metadata = [], ?int $actor_id = null ): void {
		$safe = $this->sanitize_metadata( $metadata );
		$encoded = wp_json_encode( $safe );

		$this->events->insert(
			[
				'project_id'    => $project_id,
				'actor_type'    => 'admin',
				'actor_id'      => $actor_id ?? ( function_exists( 'get_current_user_id' ) ? get_current_user_id() : null ),
				'event_type'    => $event_type,
				'metadata_json' => is_string( $encoded ) ? $encoded : '{}',
			]
		);
	}

	/**
	 * @param array<string, mixed> $metadata
	 * @return array<string, mixed>
	 */
	private function sanitize_metadata( array $metadata ): array {
		$safe = [];
		foreach ( $metadata as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}
			if ( is_scalar( $value ) || null === $value ) {
				$safe[ $key ] = is_string( $value ) ? substr( $value, 0, 500 ) : $value;
			}
		}

		return $safe;
	}
}
