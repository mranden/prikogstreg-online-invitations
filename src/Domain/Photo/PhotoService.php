<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Photo;

use PrikOgStreg\OnlineInvitations\Database\Repositories\EventRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\PhotoRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Domain\Delivery\DeliveryQueueService;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestSendTokenStore;
use PrikOgStreg\OnlineInvitations\Domain\Guest\InvitationStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicEntitlement;
use PrikOgStreg\OnlineInvitations\Domain\Wishlist\WishlistSanitizer;
use PrikOgStreg\OnlineInvitations\Public\TokenResolution;
use PrikOgStreg\OnlineInvitations\Security\Authorization;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

/**
 * Guest photo upload, moderation, download, and cleanup orchestration.
 */
final class PhotoService {

	public function __construct(
		private PhotoRepository $photos,
		private ProjectRepository $projects,
		private GuestRepository $guests,
		private EventRepository $events,
		private PhotoUploadIntentService $intents,
		private PhotoShareUploadIntentService $share_intents,
		private PhotoImageValidator $validator,
		private PhotoImageProcessor $processor,
		private PhotoStorageService $storage,
		private PhotoCleanupService $cleanup,
		private DeliveryQueueService $delivery_queue,
		private PhotoUploadRateLimiter $rate_limiter
	) {}

	/**
	 * @param array<string, mixed> $input
	 * @return array{success:bool,error?:string,intent?:string,expires_at?:int,max_files?:int}
	 */
	public function issue_intent( TokenResolution $resolution, string $raw_token, array $input = [] ): array {
		$project = $resolution->project();
		if ( ! PublicEntitlement::is_publicly_available( $project ) || empty( $project['guest_photos_enabled'] ) ) {
			return [ 'success' => false, 'error' => 'photos_disabled' ];
		}

		$token_hash = InvitationToken::hash( $raw_token );
		if ( ! $this->rate_limiter->allow( $token_hash ) ) {
			return [ 'success' => false, 'error' => 'rate_limited' ];
		}

		$guest = $this->resolve_guest( $resolution, $input, 'intent-' . $token_hash );
		if ( isset( $guest['error'] ) ) {
			return [ 'success' => false, 'error' => (string) $guest['error'] ];
		}

		/** @var array<string, mixed> $guest_row */
		$guest_row = $guest['guest'];
		$guest_id  = (int) ( $guest_row['guest_id'] ?? 0 );

		return $this->intents->issue(
			(int) $project['project_id'],
			$guest_id,
			$token_hash
		);
	}

	/**
	 * @param list<array{name:string,tmp_name:string,size:int,error:int}> $files
	 * @return array{success:bool,error?:string,uploaded?:list<array<string,mixed>>}
	 */
	public function upload(
		TokenResolution $resolution,
		string $raw_token,
		string $intent_token,
		array $files
	): array {
		$project = $resolution->project();
		if ( ! PublicEntitlement::is_publicly_available( $project ) || empty( $project['guest_photos_enabled'] ) ) {
			return [ 'success' => false, 'error' => 'photos_disabled' ];
		}

		$intent = $this->intents->verify( $intent_token, $resolution, $raw_token );
		if ( empty( $intent['success'] ) ) {
			return [ 'success' => false, 'error' => (string) ( $intent['error'] ?? 'invalid_intent' ) ];
		}

		$payload   = is_array( $intent['payload'] ?? null ) ? $intent['payload'] : [];
		$max_files = (int) ( $payload['max_files'] ?? PhotoLimits::MAX_FILES_PER_REQUEST );
		$guest_id  = (int) ( $payload['guest_id'] ?? 0 );

		if ( count( $files ) > $max_files || count( $files ) > PhotoLimits::MAX_FILES_PER_REQUEST ) {
			return [ 'success' => false, 'error' => 'too_many_files' ];
		}

		if ( [] === $files ) {
			return [ 'success' => false, 'error' => 'no_files' ];
		}

		$project_id   = (int) $project['project_id'];
		$storage_uuid = (string) ( $project['storage_uuid'] ?? '' );
		$used_bytes   = $this->photos->sum_active_bytes( $project_id );
		$uploaded     = [];

		foreach ( $files as $file ) {
			$result = $this->process_upload_file(
				$project,
				$guest_id,
				$storage_uuid,
				$used_bytes,
				$file
			);
			if ( empty( $result['success'] ) ) {
				return $result;
			}

			$used_bytes += (int) ( $result['bytes'] ?? 0 );
			$uploaded[] = $result['photo'] ?? [];
		}

		$this->cleanup->cleanup_orphan_pending( $storage_uuid );

		return [ 'success' => true, 'uploaded' => $uploaded ];
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $session
	 * @param array<string, mixed> $input
	 * @return array{success:bool,error?:string,intent?:string,expires_at?:int,max_files?:int}
	 */
	public function issue_share_intent(
		array $project,
		array $session,
		string $share_token_hash,
		array $input = []
	): array {
		if ( ! PhotoShareEntitlement::is_upload_open( $project ) ) {
			return [ 'success' => false, 'error' => 'photos_disabled' ];
		}

		if ( ! $this->rate_limiter->allow( $share_token_hash ) ) {
			return [ 'success' => false, 'error' => 'rate_limited' ];
		}

		$session_key = 'share-' . (string) ( $session['nonce'] ?? '' );
		$guest       = $this->resolve_share_guest( $project, $input, $session_key );
		if ( isset( $guest['error'] ) ) {
			return [ 'success' => false, 'error' => (string) $guest['error'] ];
		}

		/** @var array<string, mixed> $guest_row */
		$guest_row = $guest['guest'];
		$guest_id  = (int) ( $guest_row['guest_id'] ?? 0 );

		return $this->share_intents->issue( $project, $session, $share_token_hash, $guest_id );
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $session
	 * @param list<array{name:string,tmp_name:string,size:int,error:int}> $files
	 * @return array{success:bool,error?:string,uploaded?:list<array<string,mixed>>}
	 */
	public function upload_share(
		array $project,
		array $session,
		string $share_token_hash,
		string $intent_token,
		array $files
	): array {
		if ( ! PhotoShareEntitlement::is_upload_open( $project ) ) {
			return [ 'success' => false, 'error' => 'photos_disabled' ];
		}

		$intent = $this->share_intents->verify( $intent_token, $project, $session, $share_token_hash );
		if ( empty( $intent['success'] ) ) {
			return [ 'success' => false, 'error' => (string) ( $intent['error'] ?? 'invalid_intent' ) ];
		}

		$payload   = is_array( $intent['payload'] ?? null ) ? $intent['payload'] : [];
		$max_files = (int) ( $payload['max_files'] ?? PhotoLimits::MAX_FILES_PER_REQUEST );
		$guest_id  = (int) ( $payload['guest_id'] ?? 0 );

		if ( count( $files ) > $max_files || count( $files ) > PhotoLimits::MAX_FILES_PER_REQUEST ) {
			return [ 'success' => false, 'error' => 'too_many_files' ];
		}

		if ( [] === $files ) {
			return [ 'success' => false, 'error' => 'no_files' ];
		}

		$project_id   = (int) $project['project_id'];
		$storage_uuid = (string) ( $project['storage_uuid'] ?? '' );
		$used_bytes   = $this->photos->sum_active_bytes( $project_id );
		$uploaded     = [];

		foreach ( $files as $file ) {
			$result = $this->process_upload_file(
				$project,
				$guest_id,
				$storage_uuid,
				$used_bytes,
				$file
			);
			if ( empty( $result['success'] ) ) {
				return $result;
			}

			$used_bytes += (int) ( $result['bytes'] ?? 0 );
			$uploaded[] = $result['photo'] ?? [];
		}

		$this->cleanup->cleanup_orphan_pending( $storage_uuid );

		return [ 'success' => true, 'uploaded' => $uploaded ];
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int}
	 */
	public function list_gallery_page( array $project, int $page = 1, int $per_page = 20 ): array {
		$page     = max( 1, $page );
		$per_page = max( 1, min( 50, $per_page ) );

		return $this->photos->list_paginated_for_project(
			(int) $project['project_id'],
			PhotoModerationStatus::APPROVED,
			$page,
			$per_page
		);
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array{success:bool,error?:string,row?:array<string,mixed>,use_thumbnail?:bool}
	 */
	public function resolve_gallery_stream( array $project, int $photo_id, bool $thumbnail = true ): array {
		if ( ! PhotoShareEntitlement::is_gallery_public( $project ) ) {
			return [ 'success' => false, 'error' => 'forbidden' ];
		}

		$row = $this->photos->find_by_id_for_project( $photo_id, (int) $project['project_id'] );
		if (
			! is_array( $row )
			|| null !== ( $row['deleted_at_utc'] ?? null )
			|| PhotoModerationStatus::APPROVED !== (string) ( $row['moderation_status'] ?? '' )
		) {
			return [ 'success' => false, 'error' => 'not_found' ];
		}

		$thumb_path = (string) ( $row['thumbnail_path'] ?? '' );
		if ( $thumbnail && '' !== $thumb_path ) {
			return [ 'success' => true, 'row' => $row, 'use_thumbnail' => true ];
		}

		return [ 'success' => true, 'row' => $row, 'use_thumbnail' => false ];
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array{success:bool,error?:string,row?:array<string,mixed>,use_thumbnail?:bool}
	 */
	public function resolve_owner_stream( array $project, int $photo_id, Authorization $authorization, bool $thumbnail = true ): array {
		if ( ! $authorization->can_edit_project( $project ) && ! $authorization->is_support_view( $project ) ) {
			return [ 'success' => false, 'error' => 'forbidden' ];
		}

		$row = $this->photos->find_by_id_for_project( $photo_id, (int) $project['project_id'] );
		if ( ! is_array( $row ) || null !== ( $row['deleted_at_utc'] ?? null ) ) {
			return [ 'success' => false, 'error' => 'not_found' ];
		}

		$thumb_path = (string) ( $row['thumbnail_path'] ?? '' );
		if ( $thumbnail && '' !== $thumb_path ) {
			return [ 'success' => true, 'row' => $row, 'use_thumbnail' => true ];
		}

		return [ 'success' => true, 'row' => $row, 'use_thumbnail' => false ];
	}

	/**
	 * @param array<string, mixed> $project
	 * @param list<int> $photo_ids
	 * @return array{success:bool,processed:int}
	 */
	public function moderate_bulk( array $project, array $photo_ids, string $action ): array {
		$processed = 0;
		foreach ( $photo_ids as $photo_id ) {
			$result = $this->moderate( $project, (int) $photo_id, $action );
			if ( ! empty( $result['success'] ) ) {
				++$processed;
			}
		}

		return [ 'success' => true, 'processed' => $processed ];
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $context
	 * @return list<array<string, mixed>>
	 */
	public function list_for_owner( array $project, ?string $status = null ): array {
		$rows = $this->photos->list_for_project( (int) $project['project_id'], $status );
		$items = [];
		foreach ( $rows as $row ) {
			$items[] = $this->format_owner_row( $row );
		}

		return $items;
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array{success:bool,error?:string}
	 */
	public function moderate( array $project, int $photo_id, string $action ): array {
		$row = $this->photos->find_by_id_for_project( $photo_id, (int) $project['project_id'] );
		if ( ! is_array( $row ) || null !== ( $row['deleted_at_utc'] ?? null ) ) {
			return [ 'success' => false, 'error' => 'not_found' ];
		}

		$status = (string) ( $row['moderation_status'] ?? '' );
		if ( PhotoModerationStatus::PENDING !== $status && in_array( $action, [ 'approve', 'reject' ], true ) ) {
			return [ 'success' => false, 'error' => 'already_moderated' ];
		}

		if ( 'approve' === $action ) {
			return $this->approve( $project, $row );
		}

		if ( 'reject' === $action ) {
			return $this->reject( $project, $row );
		}

		if ( 'delete' === $action ) {
			return $this->delete_photo( $project, $row );
		}

		return [ 'success' => false, 'error' => 'invalid_action' ];
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array{success:bool,error?:string,row?:array<string,mixed>}
	 */
	public function resolve_download( array $project, int $photo_id, Authorization $authorization ): array {
		if ( ! $authorization->can_edit_project( $project ) && ! $authorization->is_support_view( $project ) ) {
			return [ 'success' => false, 'error' => 'forbidden' ];
		}

		$row = $this->photos->find_by_id_for_project( $photo_id, (int) $project['project_id'] );
		if ( ! is_array( $row ) || null !== ( $row['deleted_at_utc'] ?? null ) ) {
			return [ 'success' => false, 'error' => 'not_found' ];
		}

		return [ 'success' => true, 'row' => $row ];
	}

	public function erase_guest_photos( int $guest_id ): int {
		$deleted = 0;
		$sql_rows = $this->guests->find_by_id( $guest_id );
		if ( ! is_array( $sql_rows ) ) {
			return 0;
		}

		$project_id = (int) ( $sql_rows['project_id'] ?? 0 );
		$project    = $this->projects->find_by_id( $project_id );
		if ( ! is_array( $project ) ) {
			return $this->photos->delete_by_guest( $guest_id );
		}

		$all = $this->photos->list_for_project( $project_id, null, true );
		foreach ( $all as $row ) {
			if ( (int) ( $row['guest_id'] ?? 0 ) !== $guest_id ) {
				continue;
			}
			$this->delete_photo( $project, $row );
			++$deleted;
		}

		return $deleted;
	}

	/**
	 * @param array<string, mixed> $file
	 * @return array{success:bool,error?:string,bytes?:int,photo?:array<string,mixed>}
	 */
	private function process_upload_file(
		array $project,
		int $guest_id,
		string $storage_uuid,
		int $used_bytes,
		array $file
	): array {
		$original_name = $this->sanitize_filename( (string) ( $file['name'] ?? '' ) );
		if ( isset( $file['error'] ) && 0 !== (int) $file['error'] ) {
			return [ 'success' => false, 'error' => 'upload_error' ];
		}

		$tmp = (string) ( $file['tmp_name'] ?? '' );
		if ( '' === $tmp || ! is_readable( $tmp ) ) {
			return [ 'success' => false, 'error' => 'unreadable_file' ];
		}

		$bytes = (string) file_get_contents( $tmp );
		$check = $this->validator->validate_bytes( $bytes );
		if ( empty( $check['success'] ) ) {
			return [ 'success' => false, 'error' => (string) ( $check['error'] ?? 'invalid_image' ) ];
		}

		$mime = (string) ( $check['mime'] ?? 'image/jpeg' );
		$processed = $this->processor->process( $bytes, $mime );
		if ( empty( $processed['success'] ) ) {
			return [ 'success' => false, 'error' => (string) ( $processed['error'] ?? 'process_failed' ) ];
		}

		$output = (string) ( $processed['bytes'] ?? '' );
		if ( $used_bytes + strlen( $output ) > PhotoLimits::PROJECT_SOFT_LIMIT_BYTES ) {
			return [ 'success' => false, 'error' => 'project_quota_exceeded' ];
		}

		$file_uuid = $this->random_uuid();
		$stored    = $this->storage->store_pending( $storage_uuid, $file_uuid, (string) ( $processed['mime'] ?? $mime ), $output );
		if ( empty( $stored['success'] ) ) {
			return [ 'success' => false, 'error' => (string) ( $stored['error'] ?? 'write_failed' ) ];
		}

		$relative = (string) ( $stored['relative_path'] ?? '' );
		$sha256   = hash( 'sha256', $output );
		$photo_id = $this->photos->insert(
			[
				'project_id'        => (int) $project['project_id'],
				'guest_id'          => $guest_id > 0 ? $guest_id : null,
				'storage_uuid'      => $storage_uuid,
				'relative_path'     => $relative,
				'original_filename' => $original_name,
				'mime_type'         => (string) ( $processed['mime'] ?? $mime ),
				'byte_size'         => strlen( $output ),
				'width'             => (int) ( $processed['width'] ?? $check['width'] ?? 0 ),
				'height'            => (int) ( $processed['height'] ?? $check['height'] ?? 0 ),
				'sha256'            => $sha256,
				'moderation_status' => PhotoModerationStatus::PENDING,
			]
		);

		if ( $photo_id <= 0 ) {
			$this->storage->delete_file( $storage_uuid, $relative );

			return [ 'success' => false, 'error' => 'save_failed' ];
		}

		$this->record_event( $project, $guest_id, $photo_id, 'photo_uploaded' );
		$this->delivery_queue->queue_photo_notification( $project, $guest_id, $photo_id );

		$status = PhotoModerationStatus::PENDING;
		if ( PhotoShareEntitlement::auto_approve_enabled( $project ) ) {
			$row = $this->photos->find_by_id_for_project( $photo_id, (int) $project['project_id'] );
			if ( is_array( $row ) && ! empty( $this->approve( $project, $row )['success'] ) ) {
				$status = PhotoModerationStatus::APPROVED;
			}
		}

		return [
			'success' => true,
			'bytes'   => strlen( $output ),
			'photo'   => [
				'photo_id' => $photo_id,
				'status'   => $status,
			],
		];
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $row
	 * @return array{success:bool,error?:string}
	 */
	private function approve( array $project, array $row ): array {
		$storage_uuid = (string) ( $row['storage_uuid'] ?? '' );
		$relative     = (string) ( $row['relative_path'] ?? '' );
		$absolute     = $this->storage->absolute_path( $storage_uuid, $relative );
		if ( ! is_string( $absolute ) || ! is_readable( $absolute ) ) {
			return [ 'success' => false, 'error' => 'file_missing' ];
		}

		$bytes = (string) file_get_contents( $absolute );
		$mime  = (string) ( $row['mime_type'] ?? 'image/jpeg' );
		$file_uuid = $this->uuid_from_relative_path( $relative );
		$approved  = $this->storage->store_approved( $storage_uuid, $file_uuid, $mime, $bytes );
		if ( empty( $approved['success'] ) ) {
			return [ 'success' => false, 'error' => (string) ( $approved['error'] ?? 'write_failed' ) ];
		}

		$thumb_path = null;
		$thumb      = $this->processor->thumbnail( $bytes, $storage_uuid, $file_uuid );
		if ( ! empty( $thumb['success'] ) && isset( $thumb['relative_path'], $thumb['bytes'] ) ) {
			$stored_thumb = $this->storage->store_thumbnail(
				$storage_uuid,
				(string) $thumb['relative_path'],
				(string) $thumb['bytes']
			);
			if ( ! empty( $stored_thumb['success'] ) ) {
				$thumb_path = (string) $thumb['relative_path'];
			}
		}

		$this->photos->update(
			(int) $row['photo_id'],
			[
				'relative_path'     => (string) ( $approved['relative_path'] ?? $relative ),
				'thumbnail_path'    => $thumb_path,
				'moderation_status' => PhotoModerationStatus::APPROVED,
				'moderated_at_utc'  => UtcDateTime::now(),
			]
		);
		$this->storage->delete_file( $storage_uuid, $relative );
		$this->record_event( $project, (int) ( $row['guest_id'] ?? 0 ), (int) $row['photo_id'], 'photo_approved' );

		return [ 'success' => true ];
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $row
	 * @return array{success:bool,error?:string}
	 */
	private function reject( array $project, array $row ): array {
		$this->photos->update(
			(int) $row['photo_id'],
			[
				'moderation_status' => PhotoModerationStatus::REJECTED,
				'moderated_at_utc'  => UtcDateTime::now(),
			]
		);
		$this->remove_files_for_row( $row );
		$this->record_event( $project, (int) ( $row['guest_id'] ?? 0 ), (int) $row['photo_id'], 'photo_rejected' );

		return [ 'success' => true ];
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $row
	 * @return array{success:bool,error?:string}
	 */
	private function delete_photo( array $project, array $row ): array {
		if ( null !== ( $row['deleted_at_utc'] ?? null ) ) {
			return [ 'success' => true ];
		}

		$this->photos->soft_delete( (int) $row['photo_id'] );
		$this->remove_files_for_row( $row );
		$this->record_event( $project, (int) ( $row['guest_id'] ?? 0 ), (int) $row['photo_id'], 'photo_deleted' );

		return [ 'success' => true ];
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function remove_files_for_row( array $row ): void {
		$storage_uuid = (string) ( $row['storage_uuid'] ?? '' );
		$relative     = (string) ( $row['relative_path'] ?? '' );
		$thumb        = (string) ( $row['thumbnail_path'] ?? '' );
		if ( '' !== $relative ) {
			$this->storage->delete_file( $storage_uuid, $relative );
		}
		if ( '' !== $thumb ) {
			$this->storage->delete_file( $storage_uuid, $thumb );
		}
	}

	private function resolve_guest( TokenResolution $resolution, array $input, string $session_key ): array {
		$guest = $resolution->guest();
		if ( $resolution->is_personal() ) {
			if ( ! is_array( $guest ) || ! PublicEntitlement::is_guest_accessible( $guest ) ) {
				return [ 'error' => 'invalid_context' ];
			}

			return [ 'guest' => $guest ];
		}

		$replay = $this->replay_guest_id( $session_key );
		if ( $replay > 0 ) {
			$existing = $this->guests->find_by_id( $replay );
			if ( is_array( $existing ) && (int) ( $existing['project_id'] ?? 0 ) === (int) $resolution->project()['project_id'] ) {
				return [ 'guest' => $existing ];
			}
		}

		$name = WishlistSanitizer::display_name( (string) ( $input['display_name'] ?? '' ) );
		if ( '' === $name ) {
			return [ 'error' => 'missing_display_name' ];
		}

		$pair     = InvitationToken::generate();
		$guest_id = $this->guests->insert(
			[
				'project_id'          => (int) $resolution->project()['project_id'],
				'display_name'        => $name,
				'email'               => null,
				'token_hash'          => $pair['hash'],
				'token_version'       => 1,
				'invitation_status'   => InvitationStatus::OPENED,
				'is_generic_response' => 1,
			]
		);
		if ( $guest_id <= 0 ) {
			return [ 'error' => 'create_failed' ];
		}

		GuestSendTokenStore::remember( $guest_id, $pair['raw'] );
		$created = $this->guests->find_by_id( $guest_id );
		if ( ! is_array( $created ) ) {
			return [ 'error' => 'create_failed' ];
		}

		$this->remember_guest_id( $session_key, $guest_id );

		return [ 'guest' => $created ];
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $input
	 * @return array{guest?:array<string,mixed>,error?:string}
	 */
	private function resolve_share_guest( array $project, array $input, string $session_key ): array {
		$replay = $this->replay_guest_id( $session_key );
		if ( $replay > 0 ) {
			$existing = $this->guests->find_by_id( $replay );
			if ( is_array( $existing ) && (int) ( $existing['project_id'] ?? 0 ) === (int) ( $project['project_id'] ?? 0 ) ) {
				return [ 'guest' => $existing ];
			}
		}

		$name = WishlistSanitizer::display_name( (string) ( $input['display_name'] ?? '' ) );
		if ( '' === $name ) {
			return [ 'error' => 'missing_display_name' ];
		}

		$pair     = InvitationToken::generate();
		$guest_id = $this->guests->insert(
			[
				'project_id'          => (int) ( $project['project_id'] ?? 0 ),
				'display_name'        => $name,
				'email'               => null,
				'token_hash'          => $pair['hash'],
				'token_version'       => 1,
				'invitation_status'   => InvitationStatus::OPENED,
				'is_generic_response' => 1,
			]
		);
		if ( $guest_id <= 0 ) {
			return [ 'error' => 'create_failed' ];
		}

		GuestSendTokenStore::remember( $guest_id, $pair['raw'] );
		$created = $this->guests->find_by_id( $guest_id );
		if ( ! is_array( $created ) ) {
			return [ 'error' => 'create_failed' ];
		}

		$this->remember_guest_id( $session_key, $guest_id );

		return [ 'guest' => $created ];
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function format_owner_row( array $row ): array {
		$guest_name = '';
		$guest_id   = (int) ( $row['guest_id'] ?? 0 );
		if ( $guest_id > 0 ) {
			$guest = $this->guests->find_by_id( $guest_id );
			if ( is_array( $guest ) ) {
				$guest_name = (string) ( $guest['display_name'] ?? '' );
			}
		}

		return [
			'photo_id'          => (int) ( $row['photo_id'] ?? 0 ),
			'guest_name'        => $guest_name,
			'original_filename' => (string) ( $row['original_filename'] ?? '' ),
			'mime_type'         => (string) ( $row['mime_type'] ?? '' ),
			'byte_size'         => (int) ( $row['byte_size'] ?? 0 ),
			'width'             => (int) ( $row['width'] ?? 0 ),
			'height'            => (int) ( $row['height'] ?? 0 ),
			'moderation_status' => (string) ( $row['moderation_status'] ?? '' ),
			'created_at_utc'    => (string) ( $row['created_at_utc'] ?? '' ),
			'thumbnail_path'    => (string) ( $row['thumbnail_path'] ?? '' ),
			'storage_uuid'      => (string) ( $row['storage_uuid'] ?? '' ),
			'relative_path'     => (string) ( $row['relative_path'] ?? '' ),
		];
	}

	private function sanitize_filename( string $name ): string {
		$name = basename( str_replace( '\\', '/', $name ) );

		return '' !== $name ? $name : 'upload.jpg';
	}

	private function random_uuid(): string {
		$data = random_bytes( 16 );
		$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 );
		$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 );

		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
	}

	private function uuid_from_relative_path( string $relative ): string {
		$base = basename( $relative );
		$stem = preg_replace( '/\.[^.]+$/', '', $base );

		return is_string( $stem ) && '' !== $stem ? $stem : $this->random_uuid();
	}

	private function remember_guest_id( string $key, int $guest_id ): void {
		if ( function_exists( 'set_transient' ) ) {
			set_transient( 'pks_oi_photo_guest_' . md5( $key ), $guest_id, PhotoLimits::INTENT_TTL_SECONDS );
		}
	}

	private function replay_guest_id( string $key ): int {
		if ( ! function_exists( 'get_transient' ) ) {
			return 0;
		}

		return (int) get_transient( 'pks_oi_photo_guest_' . md5( $key ) );
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function record_event( array $project, int $guest_id, int $photo_id, string $type ): void {
		$this->events->insert(
			[
				'project_id' => (int) $project['project_id'],
				'guest_id'   => $guest_id > 0 ? $guest_id : null,
				'event_type' => $type,
				'payload_json' => wp_json_encode( [ 'photo_id' => $photo_id ] ),
			]
		);
	}
}
