<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Database\Repositories\EventRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageConflictException;
use PrikOgStreg\OnlineInvitations\Storage\ProjectManifest;
use PrikOgStreg\OnlineInvitations\Storage\ProjectStorage;
use PrikOgStreg\OnlineInvitations\Storage\StorageLimits;

/**
 * Loads and saves project-owned builder state via adapter + atomic storage.
 */
final class ProjectStateService {

	public const MAX_REQUEST_BYTES = StorageLimits::MAX_STATE_BYTES;

	public function __construct(
		private BuilderService $builder,
		private ProjectStorage $storage,
		private ProjectRepository $projects,
		private EventRepository $events
	) {}

	/**
	 * @param array<string, mixed> $project
	 * @return array<string, mixed>
	 */
	public function load_canonical_state( array $project ): array {
		$adapter = $this->builder->get_adapter();
		$context = $this->adapter_context( $project, 'edit' );

		if ( null !== $adapter && method_exists( $adapter, 'load_state' ) ) {
			$loaded = $adapter->load_state( $context );
			if ( is_array( $loaded ) ) {
				return $loaded;
			}
		}

		return $this->load_state_from_files( $project );
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $incoming_state
	 * @return array{state_version:int}|array{error:string,code:int}
	 */
	public function save_design_state( array $project, array $incoming_state, int $expected_version ): array {
		if ( ! ProjectEntitlement::can_edit_project( $project ) ) {
			return [ 'error' => 'entitlement_denied', 'code' => 403 ];
		}

		$encoded = json_encode( $incoming_state, JSON_UNESCAPED_SLASHES );
		if ( is_string( $encoded ) && strlen( $encoded ) > self::MAX_REQUEST_BYTES ) {
			return [ 'error' => 'payload_too_large', 'code' => 413 ];
		}

		$adapter = $this->builder->get_adapter();
		if ( null === $adapter ) {
			return [ 'error' => 'adapter_unavailable', 'code' => 503 ];
		}

		$context = $this->adapter_context( $project, 'edit' );
		$state   = $incoming_state;

		if ( method_exists( $adapter, 'save_state' ) ) {
			$saved = $adapter->save_state( $state, $context );
			if ( is_wp_error( $saved ) ) {
				return [ 'error' => (string) ( $saved->get_error_code() ?: 'invalid_state' ), 'code' => 422 ];
			}
			if ( is_array( $saved ) ) {
				$state = $saved;
			}
		} elseif ( method_exists( $adapter, 'validate_state' ) ) {
			$validated = $adapter->validate_state( $state, $context );
			if ( is_wp_error( $validated ) ) {
				return [ 'error' => (string) ( $validated->get_error_code() ?: 'invalid_state' ), 'code' => 422 ];
			}
			if ( is_array( $validated ) ) {
				$state = $validated;
			}
		}

		try {
			$result = $this->persist_canonical_state( $project, $state, $expected_version );
		} catch ( StorageConflictException $exception ) {
			return [ 'error' => 'stale_state_version', 'code' => 409 ];
		}

		$this->record_event( (int) $project['project_id'], 'project_state_saved', [ 'state_version' => $result['state_version'] ] );
		do_action( 'pks_oi_project_state_saved', (int) $project['project_id'], $result['state_version'] );

		return [ 'state_version' => (int) $result['state_version'] ];
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $state
	 * @return array{state_version:int}
	 */
	private function persist_canonical_state( array $project, array $state, int $expected_version ): array {
		$pages = [];
		$raw   = is_array( $state['page'] ?? null ) ? $state['page'] : [];

		foreach ( array_values( $raw ) as $index => $html ) {
			$pages[] = [
				'index' => $index + 1,
				'html'  => (string) $html,
			];
		}

		$state_payload = [
			'schema_version' => (string) ( $state['schema_version'] ?? $project['builder_schema_version'] ?? '1' ),
			'field'          => is_array( $state['field'] ?? null ) ? $state['field'] : [],
			'size'           => (string) ( $state['size'] ?? '' ),
			'format'         => (string) ( $state['format'] ?? '' ),
			'pages'          => array_map( static fn( array $page ): array => [ 'index' => (int) $page['index'] ], $pages ),
		];

		$state_json = json_encode( $state_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $state_json ) ) {
			throw new \InvalidArgumentException( 'invalid_state_json' );
		}

		$result = $this->storage->save_state(
			[
				'project_id'             => (int) $project['project_id'],
				'storage_uuid'           => (string) $project['storage_uuid'],
				'builder_schema_version' => (string) ( $project['builder_schema_version'] ?? '1' ),
				'product_id'             => (int) $project['product_id'],
				'template_id'            => (string) ( $project['template_id'] ?? (string) $project['product_id'] ),
				'expected_state_version' => $expected_version,
				'state_json'             => $state_json,
				'pages'                  => $pages,
			]
		);

		$this->projects->update(
			(int) $project['project_id'],
			[
				'state_version'       => (int) $result['state_version'],
				'state_manifest_path' => (string) $result['state_manifest_path'],
			]
		);

		return $result;
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array<string, mixed>
	 */
	private function load_state_from_files( array $project ): array {
		$storage_uuid = (string) $project['storage_uuid'];
		$json         = $this->storage->read_current_state( $storage_uuid, true );
		$decoded      = json_decode( $json, true );
		$manifest     = $this->storage->read_state_manifest( $storage_uuid );

		$pages = [];
		foreach ( $manifest->pages as $page ) {
			if ( ! isset( $page['editable_path'] ) ) {
				continue;
			}
			$pages[] = $this->storage->read_editable_page(
				$storage_uuid,
				(string) $page['editable_path'],
				isset( $page['editable_sha256'] ) ? (string) $page['editable_sha256'] : null
			);
		}

		return [
			'schema_version' => (string) ( is_array( $decoded ) ? ( $decoded['schema_version'] ?? '1' ) : '1' ),
			'template_id'    => (int) $project['product_id'],
			'product_id'     => (int) $project['product_id'],
			'size'           => (string) ( is_array( $decoded ) ? ( $decoded['size'] ?? '' ) : '' ),
			'format'         => (string) ( is_array( $decoded ) ? ( $decoded['format'] ?? '' ) : '' ),
			'field'          => is_array( $decoded['field'] ?? null ) ? $decoded['field'] : [],
			'page'           => $pages,
		];
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array<string, mixed>
	 */
	public function adapter_context( array $project, string $mode ): array {
		return [
			'source'        => 'online_invitation',
			'mode'          => $mode,
			'project_id'    => (int) $project['project_id'],
			'product_id'    => (int) $project['product_id'],
			'template_id'   => (int) $project['product_id'],
			'user_id'       => (int) ( $project['user_id'] ?? 0 ),
			'state_version' => (int) ( $project['state_version'] ?? 0 ),
			'locale'        => (string) ( $project['locale'] ?? 'da_DK' ),
			'is_preview'    => 'preview' === $mode,
			'is_public'     => false,
		];
	}

	/**
	 * @param array<string, mixed> $metadata
	 */
	private function record_event( int $project_id, string $event_type, array $metadata = [] ): void {
		$encoded = json_encode( $metadata, JSON_UNESCAPED_SLASHES );
		$this->events->insert(
			[
				'project_id'    => $project_id,
				'actor_type'    => 'customer',
				'event_type'    => $event_type,
				'metadata_json' => is_string( $encoded ) ? $encoded : '{}',
			]
		);
	}
}
