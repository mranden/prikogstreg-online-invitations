<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Database\Repositories\EventRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageException;
use PrikOgStreg\OnlineInvitations\Storage\ProjectStorage;
use PrikOgStreg\OnlineInvitations\Support\BuilderPageHtmlNormalizer;

/**
 * Loads project-owned builder state via adapter + atomic storage.
 */
final class ProjectStateService {

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
	 * Loads builder state for publish with editable page HTML merged from project files.
	 *
	 * @param array<string, mixed> $project
	 * @return array<string, mixed>
	 */
	public function load_state_for_publish( array $project ): array {
		$adapter = $this->builder->get_adapter();
		$context = $this->adapter_context( $project, 'public' );

		$state = null;
		if ( null !== $adapter && method_exists( $adapter, 'load_state' ) ) {
			$loaded = $adapter->load_state( $context );
			if ( is_array( $loaded ) ) {
				$state = $loaded;
			}
		}

		if ( null === $state ) {
			return $this->load_state_from_files( $project );
		}

		return $this->merge_editable_pages_into_state( $project, $state );
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $state
	 * @return array<string, mixed>
	 */
	private function merge_editable_pages_into_state( array $project, array $state ): array {
		if ( $this->state_has_substantive_pages( $state ) ) {
			return $state;
		}

		$file_state = $this->load_state_from_files( $project );
		$file_pages = is_array( $file_state['page'] ?? null ) ? $file_state['page'] : [];

		if ( [] !== $file_pages && $this->pages_have_substantive_content( $file_pages ) ) {
			$state['page'] = $file_pages;
		}

		return $state;
	}

	/**
	 * @param array<string, mixed> $state
	 */
	private function state_has_substantive_pages( array $state ): bool {
		$pages = is_array( $state['page'] ?? null ) ? $state['page'] : [];

		return $this->pages_have_substantive_content( $pages );
	}

	/**
	 * @param list<mixed> $pages
	 */
	private function pages_have_substantive_content( array $pages ): bool {
		foreach ( $pages as $html ) {
			if ( is_string( $html ) && '' !== trim( wp_strip_all_tags( $html ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public function read_design_source( array $project ): string {
		if ( (int) ( $project['state_version'] ?? 0 ) < 1 ) {
			return '';
		}

		try {
			$json    = $this->storage->read_current_state( (string) $project['storage_uuid'], false );
			$decoded = json_decode( $json, true );

			return is_array( $decoded )
				? (string) ( $decoded['design_source'] ?? ProjectDesignSource::CUSTOMER )
				: ProjectDesignSource::CUSTOMER;
		} catch ( StorageException $exception ) {
			return '';
		}
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

		return [ 'error' => 'design_read_only', 'code' => 403 ];
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array<string, mixed>
	 */
	private function load_state_from_files( array $project ): array {
		$storage_uuid = (string) $project['storage_uuid'];

		try {
			$json     = $this->storage->read_current_state( $storage_uuid, true );
			$decoded  = json_decode( $json, true );
			$manifest = $this->storage->read_state_manifest( $storage_uuid );
		} catch ( StorageException $exception ) {
			return ( new ProjectTemplateFallback( $this->builder ) )->resolve_for_product(
				(int) $project['product_id'],
				$this->adapter_context( $project, 'edit' )
			);
		}

		$pages = [];
		foreach ( $manifest->pages as $page ) {
			if ( ! isset( $page['editable_path'] ) ) {
				continue;
			}
			$pages[] = BuilderPageHtmlNormalizer::normalize(
				$this->storage->read_editable_page(
					$storage_uuid,
					(string) $page['editable_path'],
					isset( $page['editable_sha256'] ) ? (string) $page['editable_sha256'] : null
				)
			);
		}

		return [
			'schema_version' => (string) ( is_array( $decoded ) ? ( $decoded['schema_version'] ?? '1' ) : '1' ),
			'design_source'  => (string) ( is_array( $decoded ) ? ( $decoded['design_source'] ?? ProjectDesignSource::CUSTOMER ) : ProjectDesignSource::CUSTOMER ),
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
			'is_public'     => 'public' === $mode,
		];
	}
}
