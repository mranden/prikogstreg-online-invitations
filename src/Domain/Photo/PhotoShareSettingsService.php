<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Photo;

use PrikOgStreg\OnlineInvitations\Database\Repositories\PhotoRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoModerationStatus;
use PrikOgStreg\OnlineInvitations\Support\UtcDateTime;

/**
 * Owner photo-sharing configuration and status summary.
 */
final class PhotoShareSettingsService {

	public function __construct(
		private ProjectRepository $projects,
		private PhotoRepository $photos,
		private PhotoShareTokenService $share_tokens,
		private PhotoAccessCodeService $access_codes
	) {}

	/**
	 * @param array<string, mixed> $project
	 * @return array<string, mixed>
	 */
	public function summary( array $project ): array {
		$project_id = (int) ( $project['project_id'] ?? 0 );
		$raw_token  = $this->share_tokens->resolve_raw_token( $project );
		$url        = null !== $raw_token ? PhotoShareTokenService::public_url( $raw_token ) : null;

		return [
			'enabled'                  => ! empty( $project['guest_photos_enabled'] ),
			'share_ready'              => PhotoShareEntitlement::is_share_ready( $project ),
			'owner_share_configured'   => PhotoShareEntitlement::is_owner_share_configured( $project ),
			'upload_open'              => PhotoShareEntitlement::is_upload_open( $project ),
			'gallery_public'           => PhotoShareEntitlement::is_gallery_public( $project ),
			'auto_approve_enabled'     => PhotoShareEntitlement::auto_approve_enabled( $project ),
			'has_access_code'          => $this->access_codes->has_code( $project ),
			'display_access_code'      => PhotoAccessCodeDisplayStore::read( $project_id ),
			'share_url'                => $url,
			'wall_url'                 => null !== $raw_token ? PhotoShareTokenService::wall_url( $raw_token ) : null,
			'pending_count'            => count( $this->photos->list_for_project( $project_id, PhotoModerationStatus::PENDING ) ),
			'approved_count'           => count( $this->photos->list_for_project( $project_id, PhotoModerationStatus::APPROVED ) ),
			'rejected_count'           => count( $this->photos->list_for_project( $project_id, PhotoModerationStatus::REJECTED ) ),
			'storage_bytes'            => $this->photos->sum_active_bytes( $project_id ),
			'upload_closes_at_utc'     => (string) ( $project['photo_upload_closes_at_utc'] ?? '' ),
			'gallery_public_enabled'   => ! empty( $project['photo_gallery_public_enabled'] ),
		];
	}

	/**
	 * @param array<string, mixed> $project
	 * @param array<string, mixed> $input
	 * @return array{success:bool,error?:string,rotated_token?:string,rotated_url?:string}
	 */
	public function save_settings( array $project, array $input ): array {
		$project_id = (int) ( $project['project_id'] ?? 0 );
		$enabled    = ! empty( $input['guest_photos_enabled'] );

		$data = [
			'guest_photos_enabled'           => $enabled ? 1 : 0,
			'photo_gallery_public_enabled'   => ! empty( $input['photo_gallery_public_enabled'] ) ? 1 : 0,
			'photo_auto_approve_enabled'     => ! empty( $input['photo_auto_approve_enabled'] ) ? 1 : 0,
		];

		$closes = sanitize_text_field( (string) ( $input['photo_upload_closes_at_utc'] ?? '' ) );
		if ( '' !== $closes ) {
			$parsed = $this->parse_close_datetime( $closes, (string) ( $project['timezone'] ?? 'Europe/Copenhagen' ) );
			$data['photo_upload_closes_at_utc'] = $parsed;
		} else {
			$data['photo_upload_closes_at_utc'] = null;
		}

		$this->projects->update( $project_id, $data );

		$result = [ 'success' => true ];

		if ( $enabled ) {
			$rotated = $this->share_tokens->ensure_token( array_merge( $project, $data ) );
			$result['rotated_token'] = $rotated['token'];
			$result['rotated_url']   = $rotated['url'];
		}

		$code    = (string) ( $input['photo_access_code'] ?? '' );
		$confirm = (string) ( $input['photo_access_code_confirm'] ?? '' );
		if ( '' !== trim( $code ) ) {
			$code_result = $this->access_codes->set_code(
				array_merge( $project, $data ),
				$code,
				$confirm
			);
			if ( empty( $code_result['success'] ) ) {
				return $code_result;
			}
		}

		return $result;
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array{token:string,url:string,version:int}
	 */
	public function rotate_share_link( array $project ): array {
		return $this->share_tokens->rotate( $project );
	}

	private function parse_close_datetime( string $value, string $timezone ): ?string {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}

		try {
			$tz = new \DateTimeZone( '' !== $timezone ? $timezone : 'Europe/Copenhagen' );
			$dt = new \DateTime( $value, $tz );
			$dt->setTimezone( new \DateTimeZone( 'UTC' ) );

			return $dt->format( 'Y-m-d H:i:s' );
		} catch ( \Throwable ) {
			return null;
		}
	}
}
