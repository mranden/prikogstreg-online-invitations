<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Storage;

use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageChecksumException;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageConflictException;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageException;
use PrikOgStreg\OnlineInvitations\Storage\Exception\StorageValidationException;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

/**
 * Private project file storage — manifests, state, pages, publish snapshots.
 */
final class ProjectStorage {

	public function __construct(
		private StoragePath $paths,
		private AtomicFileWriter $writer,
		private SafeFileReader $reader,
		private StorageCleanup $cleanup
	) {}

	public function create_project_directories( string $storage_uuid ): void {
		$this->paths->assert_storage_uuid( $storage_uuid );

		$directories = [
			$this->paths->project_root( $storage_uuid ),
			$this->paths->project_root( $storage_uuid ) . '/state',
			$this->paths->project_root( $storage_uuid ) . '/pages/editable',
			$this->paths->project_root( $storage_uuid ) . '/pages/published',
			$this->paths->project_root( $storage_uuid ) . '/published',
			$this->paths->project_root( $storage_uuid ) . '/previews',
			$this->paths->project_root( $storage_uuid ) . '/wishlist/images',
			$this->paths->project_root( $storage_uuid ) . '/photos/pending',
			$this->paths->project_root( $storage_uuid ) . '/photos/approved',
			$this->paths->project_root( $storage_uuid ) . '/photos/thumbnails',
			$this->paths->project_root( $storage_uuid ) . '/envelope',
			$this->paths->project_tmp_dir( $storage_uuid ),
		];

		foreach ( $directories as $directory ) {
			StorageDirectory::ensure( $directory );
		}
	}

	/**
	 * @param array{
	 *     project_id:int,
	 *     storage_uuid:string,
	 *     builder_schema_version:string,
	 *     product_id:int,
	 *     template_id:string,
	 *     expected_state_version:int,
	 *     state_json:string,
	 *     pages:list<array{index:int,html:string}>
	 * } $context
	 * @return array{
	 *     state_version:int,
	 *     state_manifest_path:string,
	 *     state_sha256:string,
	 *     pages:list<array<string,mixed>>
	 * }
	 */
	public function save_state( array $context ): array {
		$this->paths->assert_storage_uuid( $context['storage_uuid'] );
		$this->create_project_directories( $context['storage_uuid'] );

		$this->assert_utf8( $context['state_json'], 'state_json' );
		$this->assert_max_bytes( $context['state_json'], StorageLimits::MAX_STATE_BYTES, 'state_json' );

		$current_manifest = $this->try_read_state_manifest( $context['storage_uuid'] );
		$current_version  = $current_manifest?->state_version ?? 0;

		if ( $current_version !== $context['expected_state_version'] ) {
			throw new StorageConflictException(
				sprintf(
					'Expected state version %d but current version is %d.',
					$context['expected_state_version'],
					$current_version
				)
			);
		}

		$this->preserve_previous_state( $context['storage_uuid'] );

		$page_entries = [];
		foreach ( $context['pages'] as $page ) {
			$index = (int) $page['index'];
			$html  = (string) $page['html'];
			$this->assert_utf8( $html, 'page_html' );
			$this->assert_max_bytes( $html, StorageLimits::MAX_PAGE_BYTES, 'page_html' );

			$relative = $this->paths->editable_page_path( $index );
			$absolute = $this->paths->absolute_from_relative( $context['storage_uuid'], $relative );
			$checksum = $this->writer->write( $absolute, $html );

			$page_entries[] = [
				'index'           => $index,
				'editable_path'   => $relative,
				'editable_sha256' => $checksum,
			];
		}

		$state_relative = StoragePath::STATE_CURRENT;
		$state_absolute = $this->paths->absolute_from_relative( $context['storage_uuid'], $state_relative );
		$state_sha256     = $this->writer->write( $state_absolute, $context['state_json'] );

		$new_version = $current_version + 1;
		$manifest    = new ProjectManifest(
			$context['project_id'],
			$context['storage_uuid'],
			$context['builder_schema_version'],
			$new_version,
			$context['product_id'],
			$context['template_id'],
			$page_entries,
			UtcDateTime::now(),
			$current_manifest?->published_version,
			$state_sha256
		);

		$manifest_absolute = $this->paths->absolute_from_relative( $context['storage_uuid'], ProjectManifest::RELATIVE_STATE_PATH );
		$this->writer->write( $manifest_absolute, $manifest->to_json() );

		$this->cleanup->cleanup_abandoned_temp_files( $context['storage_uuid'] );

		return [
			'state_version'         => $new_version,
			'state_manifest_path'   => ProjectManifest::RELATIVE_STATE_PATH,
			'state_sha256'          => $state_sha256,
			'pages'                 => $page_entries,
		];
	}

	/**
	 * @param array{
	 *     project_id:int,
	 *     storage_uuid:string,
	 *     builder_schema_version:string,
	 *     product_id:int,
	 *     template_id:string,
	 *     expected_state_version:int,
	 *     published_version:int,
	 *     pages:list<array{index:int,html:string}>
	 * } $context
	 * @return array{
	 *     published_version:int,
	 *     published_manifest_path:string,
	 *     pages:list<array<string,mixed>>
	 * }
	 */
	public function publish_snapshot( array $context ): array {
		$this->paths->assert_storage_uuid( $context['storage_uuid'] );
		$this->create_project_directories( $context['storage_uuid'] );

		$state_manifest = $this->read_state_manifest( $context['storage_uuid'] );
		if ( $state_manifest->state_version !== $context['expected_state_version'] ) {
			throw new StorageConflictException( 'Cannot publish with stale state version.' );
		}

		$page_entries = [];
		foreach ( $context['pages'] as $page ) {
			$index = (int) $page['index'];
			$html  = (string) $page['html'];
			$this->assert_utf8( $html, 'published_page_html' );
			$this->assert_max_bytes( $html, StorageLimits::MAX_PAGE_BYTES, 'published_page_html' );

			$relative = $this->paths->published_page_path( $index );
			$absolute = $this->paths->absolute_from_relative( $context['storage_uuid'], $relative );
			$checksum = $this->writer->write( $absolute, $html );

			$page_entries[] = [
				'index'            => $index,
				'published_path'   => $relative,
				'published_sha256' => $checksum,
			];
		}

		$manifest = new ProjectManifest(
			$context['project_id'],
			$context['storage_uuid'],
			$context['builder_schema_version'],
			$state_manifest->state_version,
			$context['product_id'],
			$context['template_id'],
			$page_entries,
			UtcDateTime::now(),
			$context['published_version']
		);

		$manifest_absolute = $this->paths->absolute_from_relative( $context['storage_uuid'], ProjectManifest::RELATIVE_PUBLISHED_PATH );
		$this->writer->write( $manifest_absolute, $manifest->to_json() );

		return [
			'published_version'         => $context['published_version'],
			'published_manifest_path'   => ProjectManifest::RELATIVE_PUBLISHED_PATH,
			'pages'                     => $page_entries,
		];
	}

	public function read_current_state( string $storage_uuid, bool $verify_checksum = true ): string {
		$manifest = $this->read_state_manifest( $storage_uuid );
		$absolute = $this->paths->absolute_from_relative( $storage_uuid, StoragePath::STATE_CURRENT );

		if ( $verify_checksum && null !== $manifest->state_sha256 && '' !== $manifest->state_sha256 ) {
			return $this->reader->read_verified( $absolute, $manifest->state_sha256 );
		}

		return $this->reader->read( $absolute );
	}

	public function read_previous_state( string $storage_uuid ): ?string {
		$absolute = $this->paths->absolute_from_relative( $storage_uuid, StoragePath::STATE_PREVIOUS );
		if ( ! is_readable( $absolute ) ) {
			return null;
		}

		return $this->reader->read( $absolute );
	}

	public function read_state_manifest( string $storage_uuid ): ProjectManifest {
		$manifest = $this->try_read_state_manifest( $storage_uuid );
		if ( null === $manifest ) {
			throw new StorageException( 'State manifest not found.' );
		}

		return $manifest;
	}

	public function read_published_manifest( string $storage_uuid, bool $verify_checksums = true ): ProjectManifest {
		$absolute = $this->paths->absolute_from_relative( $storage_uuid, ProjectManifest::RELATIVE_PUBLISHED_PATH );
		$manifest = ProjectManifest::read_file( $absolute );

		if ( $verify_checksums ) {
			$this->verify_manifest_page_checksums( $storage_uuid, $manifest, 'published_path', 'published_sha256' );
		}

		return $manifest;
	}

	public function try_read_poster_manifest( string $storage_uuid ): ?PublishedPosterManifest {
		try {
			$absolute = $this->paths->absolute_from_relative( $storage_uuid, PublishedPosterManifest::RELATIVE_PATH );
		} catch ( \Throwable ) {
			return null;
		}

		if ( ! is_readable( $absolute ) ) {
			return null;
		}

		try {
			return PublishedPosterManifest::read_file( $absolute );
		} catch ( \Throwable ) {
			return null;
		}
	}

	public function write_published_asset( string $storage_uuid, string $relative_path, string $content ): string {
		$this->paths->assert_relative_path( $relative_path );
		if ( ! str_starts_with( $relative_path, 'published/' ) ) {
			throw new StorageException( 'Published asset path must live under published/.' );
		}

		$this->assert_utf8( $content, 'published_asset' );
		$this->assert_max_bytes( $content, StorageLimits::MAX_PAGE_BYTES, 'published_asset' );

		$absolute = $this->paths->absolute_from_relative( $storage_uuid, $relative_path );

		return $this->writer->write( $absolute, $content );
	}

	public function read_published_asset( string $storage_uuid, string $relative_path ): string {
		$this->paths->assert_relative_path( $relative_path );
		if ( ! str_starts_with( $relative_path, 'published/' ) ) {
			throw new StorageException( 'Published asset path must live under published/.' );
		}

		$absolute = $this->paths->absolute_from_relative( $storage_uuid, $relative_path );

		return $this->reader->read( $absolute );
	}

	public function verify_manifest_integrity( string $storage_uuid ): void {
		$manifest = $this->read_state_manifest( $storage_uuid );
		$this->verify_manifest_page_checksums( $storage_uuid, $manifest, 'editable_path', 'editable_sha256' );

		if ( null !== $manifest->state_sha256 && '' !== $manifest->state_sha256 ) {
			$state_absolute = $this->paths->absolute_from_relative( $storage_uuid, StoragePath::STATE_CURRENT );
			$this->reader->read_verified( $state_absolute, $manifest->state_sha256 );
		}
	}

	public function delete_project_storage( string $storage_uuid ): bool {
		return $this->cleanup->delete_project_tree( $storage_uuid );
	}

	/**
	 * Initial import from canonical builder state (order-item payload).
	 *
	 * @param array{
	 *     project_id:int,
	 *     storage_uuid:string,
	 *     builder_schema_version:string,
	 *     product_id:int,
	 *     template_id:string
	 * } $project_context
	 * @param array<string, mixed> $canonical_state
	 * @return array{
	 *     state_version:int,
	 *     state_manifest_path:string,
	 *     state_sha256:string,
	 *     pages:list<array<string,mixed>>
	 * }
	 */
	public function import_from_builder_state( array $project_context, array $canonical_state ): array {
		$pages     = [];
		$raw_pages = is_array( $canonical_state['page'] ?? null ) ? $canonical_state['page'] : [];

		foreach ( array_values( $raw_pages ) as $index => $html ) {
			$pages[] = [
				'index' => $index + 1,
				'html'  => (string) $html,
			];
		}

		if ( [] === $pages ) {
			throw new StorageValidationException( 'Builder import requires at least one page.', 'missing_page_payload' );
		}

		$thumbnails = is_array( $canonical_state['_pages_thumbnails'] ?? null )
			? $canonical_state['_pages_thumbnails']
			: [];

		$state_payload = [
			'schema_version'     => (string) ( $canonical_state['schema_version'] ?? $project_context['builder_schema_version'] ),
			'field'              => is_array( $canonical_state['field'] ?? null ) ? $canonical_state['field'] : [],
			'size'               => (string) ( $canonical_state['size'] ?? $canonical_state['pa_bpp_size'] ?? '' ),
			'format'             => (string) ( $canonical_state['format'] ?? $canonical_state['pa_bpp_format'] ?? '' ),
			'product_id'         => (int) ( $canonical_state['product_id'] ?? $project_context['product_id'] ),
			'template_id'        => (string) ( $canonical_state['template_id'] ?? $project_context['template_id'] ),
			'page_count'         => count( $pages ),
			'pages'              => array_map(
				static fn( array $page ): array => [ 'index' => (int) $page['index'] ],
				$pages
			),
			'thumbnails'         => $thumbnails,
		];

		$state_json = json_encode( $state_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $state_json ) ) {
			throw new StorageValidationException( 'Unable to encode builder state.', 'invalid_state_json' );
		}

		return $this->save_state(
			[
				'project_id'             => (int) $project_context['project_id'],
				'storage_uuid'           => (string) $project_context['storage_uuid'],
				'builder_schema_version' => (string) $project_context['builder_schema_version'],
				'product_id'             => (int) $project_context['product_id'],
				'template_id'            => (string) $project_context['template_id'],
				'expected_state_version' => 0,
				'state_json'             => $state_json,
				'pages'                  => $pages,
			]
		);
	}

	/**
	 * Atomic initial import: builder state plus envelope snapshot.
	 *
	 * @param array{
	 *     project_id:int,
	 *     storage_uuid:string,
	 *     builder_schema_version:string,
	 *     product_id:int,
	 *     template_id:string
	 * } $project_context
	 * @param array<string, mixed> $canonical_state
	 * @param array<string, mixed> $envelope_snapshot
	 * @return array{
	 *     state_version:int,
	 *     state_manifest_path:string,
	 *     state_sha256:string,
	 *     envelope_manifest_path:string,
	 *     pages:list<array<string,mixed>>
	 * }
	 */
	public function import_complete_snapshot( array $project_context, array $canonical_state, array $envelope_snapshot ): array {
		$builder = $this->import_from_builder_state( $project_context, $canonical_state );
		$envelope = $this->import_envelope_snapshot( $project_context, $envelope_snapshot );

		return array_merge( $builder, $envelope );
	}

	/**
	 * @param array{
	 *     project_id:int,
	 *     storage_uuid:string
	 * } $project_context
	 * @param array<string, mixed> $envelope_snapshot
	 * @return array{envelope_manifest_path:string}
	 */
	public function import_envelope_snapshot( array $project_context, array $envelope_snapshot ): array {
		$storage_uuid = (string) $project_context['storage_uuid'];
		$this->paths->assert_storage_uuid( $storage_uuid );
		$this->create_project_directories( $storage_uuid );

		$snapshot = array_merge(
			$envelope_snapshot,
			[
				'project_id'   => (int) $project_context['project_id'],
				'storage_uuid' => $storage_uuid,
			]
		);

		$attachment_id = max( 0, (int) ( $snapshot['attachment_id'] ?? 0 ) );
		if ( $attachment_id > 0 ) {
			$copied = $this->copy_attachment_to_envelope( $storage_uuid, $attachment_id );
			if ( $copied['success'] ) {
				$snapshot['media_storage']  = EnvelopeManifest::MEDIA_PROJECT_COPY;
				$snapshot['image_path']     = (string) $copied['relative_path'];
				$snapshot['image_sha256']   = (string) $copied['sha256'];
			} else {
				$snapshot['media_storage'] = EnvelopeManifest::MEDIA_ATTACHMENT;
			}
		}

		$manifest = EnvelopeManifest::from_snapshot( $snapshot );
		$absolute = $this->paths->absolute_from_relative( $storage_uuid, EnvelopeManifest::RELATIVE_PATH );
		$this->writer->write( $absolute, $manifest->to_json() );

		if ( null !== $manifest->image_path && null !== $manifest->image_sha256 && '' !== $manifest->image_sha256 ) {
			$image_absolute = $this->paths->absolute_from_relative( $storage_uuid, $manifest->image_path );
			$this->reader->read_verified( $image_absolute, $manifest->image_sha256 );
		}

		return [
			'envelope_manifest_path' => EnvelopeManifest::RELATIVE_PATH,
		];
	}

	public function read_envelope_manifest( string $storage_uuid, bool $verify_checksum = true ): EnvelopeManifest {
		$absolute = $this->paths->absolute_from_relative( $storage_uuid, EnvelopeManifest::RELATIVE_PATH );
		$manifest = EnvelopeManifest::read_file( $absolute );

		if ( $verify_checksum && null !== $manifest->image_path && null !== $manifest->image_sha256 && '' !== $manifest->image_sha256 ) {
			$image_absolute = $this->paths->absolute_from_relative( $storage_uuid, $manifest->image_path );
			$this->reader->read_verified( $image_absolute, $manifest->image_sha256 );
		}

		return $manifest;
	}

	public function try_read_envelope_manifest( string $storage_uuid ): ?EnvelopeManifest {
		$absolute = $this->paths->absolute_from_relative( $storage_uuid, EnvelopeManifest::RELATIVE_PATH );
		if ( ! is_readable( $absolute ) ) {
			return null;
		}

		return EnvelopeManifest::read_file( $absolute );
	}

	public function envelope_image_absolute_path( string $storage_uuid ): ?string {
		$manifest = $this->try_read_envelope_manifest( $storage_uuid );
		if ( null === $manifest || EnvelopeManifest::MEDIA_PROJECT_COPY !== $manifest->media_storage ) {
			return null;
		}

		if ( null === $manifest->image_path || '' === $manifest->image_path ) {
			return null;
		}

		try {
			return $this->paths->absolute_from_relative( $storage_uuid, $manifest->image_path );
		} catch ( \Throwable ) {
			return null;
		}
	}

	private function resolve_attachment_source_path( int $attachment_id ): string {
		$path = '';
		if ( function_exists( 'get_attached_file' ) ) {
			$attached = get_attached_file( $attachment_id );
			if ( is_string( $attached ) ) {
				$path = $attached;
			}
		}

		/**
		 * @var string $path
		 */
		return (string) \apply_filters( 'pks_oi/envelope_attachment_source_path', $path, $attachment_id );
	}

	/**
	 * @return array{success:bool,relative_path?:string,sha256?:string}
	 */
	private function copy_attachment_to_envelope( string $storage_uuid, int $attachment_id ): array {
		$source = $this->resolve_attachment_source_path( $attachment_id );
		if ( '' === $source ) {
			return [ 'success' => false ];
		}

		$extension = strtolower( pathinfo( $source, PATHINFO_EXTENSION ) );
		if ( '' === $extension || ! preg_match( '/^[a-z0-9]{1,8}$/', $extension ) ) {
			$extension = 'jpg';
		}

		$relative = 'envelope/envelope-image.' . $extension;

		try {
			$this->paths->assert_relative_path( $relative );
			$bytes = file_get_contents( $source );
			if ( false === $bytes || '' === $bytes ) {
				return [ 'success' => false ];
			}

			$this->assert_max_bytes( $bytes, StorageLimits::MAX_PAGE_BYTES, 'envelope_image' );
			$absolute = $this->paths->absolute_from_relative( $storage_uuid, $relative );
			$checksum = $this->writer->write( $absolute, $bytes );

			return [
				'success'       => true,
				'relative_path' => $relative,
				'sha256'        => $checksum,
			];
		} catch ( \Throwable ) {
			return [ 'success' => false ];
		}
	}

	public function read_editable_page( string $storage_uuid, string $relative_path, ?string $checksum = null ): string {
		$absolute = $this->paths->absolute_from_relative( $storage_uuid, $relative_path );
		if ( null !== $checksum && '' !== $checksum ) {
			return $this->reader->read_verified( $absolute, $checksum );
		}

		return $this->reader->read( $absolute );
	}

	public function read_published_page( string $storage_uuid, string $relative_path, ?string $checksum = null ): string {
		$absolute = $this->paths->absolute_from_relative( $storage_uuid, $relative_path );
		if ( null !== $checksum && '' !== $checksum ) {
			return $this->reader->read_verified( $absolute, $checksum );
		}

		return $this->reader->read( $absolute );
	}

	private function try_read_state_manifest( string $storage_uuid ): ?ProjectManifest {
		$absolute = $this->paths->absolute_from_relative( $storage_uuid, ProjectManifest::RELATIVE_STATE_PATH );
		if ( ! is_readable( $absolute ) ) {
			return null;
		}

		return ProjectManifest::read_file( $absolute );
	}

	private function preserve_previous_state( string $storage_uuid ): void {
		$current  = $this->paths->absolute_from_relative( $storage_uuid, StoragePath::STATE_CURRENT );
		$previous = $this->paths->absolute_from_relative( $storage_uuid, StoragePath::STATE_PREVIOUS );

		if ( ! is_readable( $current ) ) {
			return;
		}

		$content = $this->reader->read( $current );
		$this->writer->write( $previous, $content );
	}

	/**
	 * @param 'editable_path'|'published_path' $path_key
	 * @param 'editable_sha256'|'published_sha256' $checksum_key
	 */
	private function verify_manifest_page_checksums(
		string $storage_uuid,
		ProjectManifest $manifest,
		string $path_key,
		string $checksum_key
	): void {
		foreach ( $manifest->pages as $page ) {
			if ( ! isset( $page[ $path_key ], $page[ $checksum_key ] ) ) {
				continue;
			}

			$absolute = $this->paths->absolute_from_relative( $storage_uuid, (string) $page[ $path_key ] );
			$this->reader->read_verified( $absolute, (string) $page[ $checksum_key ] );
		}
	}

	private function assert_utf8( string $content, string $field ): void {
		if ( ! mb_check_encoding( $content, 'UTF-8' ) ) {
			throw new StorageValidationException( 'Invalid UTF-8 in ' . $field . '.', 'invalid_utf8' );
		}
	}

	private function assert_max_bytes( string $content, int $max_bytes, string $field ): void {
		if ( strlen( $content ) > $max_bytes ) {
			throw new StorageValidationException( $field . ' exceeds maximum allowed size.', 'oversized_payload' );
		}
	}
}
