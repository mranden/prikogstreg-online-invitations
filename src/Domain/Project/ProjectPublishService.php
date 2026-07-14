<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Database\Repositories\EventRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Public\PublishedPosterAssetSnapshotter;
use PrikOgStreg\OnlineInvitations\Security\PublishedHtmlSanitizer;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageException;
use PrikOgStreg\OnlineInvitations\Storage\ProjectStorage;
use PrikOgStreg\OnlineInvitations\Support\PublishedHtmlValidator;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

/**
 * Publishes and unpublishes project snapshots.
 */
final class ProjectPublishService {

	public function __construct(
		private BuilderService $builder,
		private ProjectStorage $storage,
		private ProjectRepository $projects,
		private ProjectStateService $state_service,
		private EventRepository $events
	) {}

	/**
	 * @param array<string, mixed> $project
	 * @return array{success:bool,error?:string,code?:int,published_version?:int}
	 */
	public function publish( array $project ): array {
		if ( ! ProjectEntitlement::can_publish_project( $project ) ) {
			return [ 'success' => false, 'error' => 'publish_requirements_missing', 'code' => 422 ];
		}

		$state      = $this->state_service->load_state_for_publish( $project );
		$pages_html = $this->render_public_pages( $project, $state );
		if ( isset( $pages_html['error'] ) ) {
			return [ 'success' => false, 'error' => (string) $pages_html['error'], 'code' => 422 ];
		}

		$published_version = max( 1, (int) ( $project['published_version'] ?? 0 ) + 1 );

		try {
			$result = $this->storage->publish_snapshot(
				[
					'project_id'             => (int) $project['project_id'],
					'storage_uuid'           => (string) $project['storage_uuid'],
					'builder_schema_version' => (string) ( $project['builder_schema_version'] ?? '1' ),
					'product_id'             => (int) $project['product_id'],
					'template_id'            => (string) ( $project['template_id'] ?? (string) $project['product_id'] ),
					'expected_state_version' => (int) ( $project['state_version'] ?? 0 ),
					'published_version'      => $published_version,
					'pages'                  => $pages_html['pages'],
				]
			);
		} catch ( StorageException $exception ) {
			return [ 'success' => false, 'error' => $exception->code_key ?? 'publish_failed', 'code' => 500 ];
		}

		$this->projects->update(
			(int) $project['project_id'],
			[
				'publication_status'      => PublicationStatus::PUBLISHED,
				'published_version'       => (int) $result['published_version'],
				'published_manifest_path' => (string) $result['published_manifest_path'],
				'published_at_utc'        => UtcDateTime::now(),
			]
		);

		$this->record_event( (int) $project['project_id'], 'project_published', [ 'published_version' => $published_version ] );
		do_action( 'pks_oi_project_published', (int) $project['project_id'], $published_version );

		( new PublishedPosterAssetSnapshotter( $this->storage ) )->snapshot( $project, $state, $pages_html['pages'] );

		return [ 'success' => true, 'published_version' => $published_version ];
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array{success:bool}
	 */
	public function unpublish( array $project ): array {
		if ( ! ProjectEntitlement::can_edit_project( $project ) ) {
			return [ 'success' => false, 'error' => 'entitlement_denied' ];
		}

		$this->projects->update(
			(int) $project['project_id'],
			[ 'publication_status' => PublicationStatus::UNPUBLISHED ]
		);

		$this->record_event( (int) $project['project_id'], 'project_unpublished', [] );
		do_action( 'pks_oi_project_unpublished', (int) $project['project_id'] );

		return [ 'success' => true ];
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $state
	 * @return array{pages:list<array{index:int,html:string}>}|array{error:string}
	 */
	private function render_public_pages( array $project, array $state ): array {
		$adapter = $this->builder->get_adapter();
		$context = $this->state_service->adapter_context( $project, 'public' );

		if ( null !== $adapter && method_exists( $adapter, 'render_public_html' ) ) {
			$html = $adapter->render_public_html( $state, $context );
			if ( is_wp_error( $html ) ) {
				return [ 'error' => (string) ( $html->get_error_code() ?: 'public_html_failed' ) ];
			}

			try {
				$sanitized = PublishedHtmlSanitizer::sanitize( (string) $html );
			} catch ( \InvalidArgumentException $exception ) {
				return [ 'error' => 'published_html_unsafe' ];
			}

			if ( ! PublishedHtmlValidator::has_visible_content( $sanitized ) ) {
				$fallback = $this->render_pages_from_state( $state );
				if ( isset( $fallback['error'] ) ) {
					return $fallback;
				}

				return $this->validate_pages_or_error( $fallback['pages'] );
			}

			return $this->validate_pages_or_error( [ [ 'index' => 1, 'html' => $sanitized ] ] );
		}

		$result = $this->render_pages_from_state( $state );
		if ( isset( $result['error'] ) ) {
			return $result;
		}

		return $this->validate_pages_or_error( $result['pages'] );
	}

	/**
	 * @param array<string, mixed> $state
	 * @return array{pages:list<array{index:int,html:string}>}|array{error:string}
	 */
	private function render_pages_from_state( array $state ): array {
		$pages     = [];
		$raw_pages = is_array( $state['page'] ?? null ) ? $state['page'] : [];

		foreach ( array_values( $raw_pages ) as $index => $html ) {
			try {
				$sanitized = PublishedHtmlSanitizer::sanitize( (string) $html );
			} catch ( \InvalidArgumentException $exception ) {
				return [ 'error' => 'published_html_unsafe' ];
			}

			$pages[] = [ 'index' => $index + 1, 'html' => $sanitized ];
		}

		if ( [] === $pages ) {
			return [ 'error' => 'missing_pages' ];
		}

		return [ 'pages' => $pages ];
	}

	/**
	 * @param list<array{index:int,html:string}> $pages
	 * @return array{pages:list<array{index:int,html:string}>}|array{error:string}
	 */
	private function validate_pages_or_error( array $pages ): array {
		foreach ( $pages as $page ) {
			if ( ! PublishedHtmlValidator::has_visible_content( (string) ( $page['html'] ?? '' ) ) ) {
				return [ 'error' => 'empty_published_html' ];
			}
		}

		return [ 'pages' => $pages ];
	}

	/**
	 * @param array<string, mixed> $metadata
	 */
	private function record_event( int $project_id, string $event_type, array $metadata ): void {
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
