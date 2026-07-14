<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Photo;

use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;

/**
 * Generates, rotates, and resolves dedicated photo share URLs.
 */
final class PhotoShareTokenService {

	public const META_RAW_TOKEN = '_pks_oi_photo_share_token_raw';

	public function __construct(
		private ProjectRepository $projects
	) {}

	public static function public_path( string $raw_token ): string {
		return '/photos/' . rawurlencode( $raw_token ) . '/';
	}

	public static function wall_path( string $raw_token ): string {
		return '/photos/' . rawurlencode( $raw_token ) . '/wall/';
	}

	public static function public_url( string $raw_token ): string {
		if ( function_exists( 'home_url' ) ) {
			return home_url( self::public_path( $raw_token ) );
		}

		return self::public_path( $raw_token );
	}

	public static function wall_url( string $raw_token ): string {
		if ( function_exists( 'home_url' ) ) {
			return home_url( self::wall_path( $raw_token ) );
		}

		return self::wall_path( $raw_token );
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array{token:string,url:string,version:int}
	 */
	public function ensure_token( array $project ): array {
		$existing = $this->resolve_raw_token( $project );
		if ( null !== $existing ) {
			return [
				'token'   => $existing,
				'url'     => self::public_url( $existing ),
				'version' => (int) ( $project['photo_share_token_version'] ?? 1 ),
			];
		}

		return $this->rotate( $project );
	}

	/**
	 * @param array<string, mixed> $project
	 * @return array{token:string,url:string,version:int}
	 */
	public function rotate( array $project ): array {
		$pair    = InvitationToken::generate();
		$version = max( 1, (int) ( $project['photo_share_token_version'] ?? 1 ) + 1 );
		$project_id = (int) ( $project['project_id'] ?? 0 );

		$this->projects->update(
			$project_id,
			[
				'photo_share_token_hash'    => $pair['hash'],
				'photo_share_token_version' => $version,
			]
		);

		$this->store_raw_token( $project_id, $pair['raw'] );

		do_action( 'pks_oi_photo_share_token_rotated', $project_id, $version );

		return [
			'token'   => $pair['raw'],
			'url'     => self::public_url( $pair['raw'] ),
			'version' => $version,
		];
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public function revoke( array $project ): void {
		$project_id = (int) ( $project['project_id'] ?? 0 );

		$this->projects->update(
			$project_id,
			[
				'photo_share_token_hash'    => null,
				'photo_share_token_version' => max( 1, (int) ( $project['photo_share_token_version'] ?? 1 ) + 1 ),
			]
		);

		delete_post_meta( $project_id, self::META_RAW_TOKEN );

		do_action( 'pks_oi_photo_share_token_revoked', $project_id );
	}

	public function resolve_by_raw_token( string $raw_token ): ?array {
		if ( ! InvitationToken::is_valid_format( $raw_token ) ) {
			return null;
		}

		return $this->projects->find_by_photo_share_token_hash( InvitationToken::hash( $raw_token ) );
	}

	/**
	 * @param array<string, mixed> $project
	 */
	public function resolve_raw_token( array $project ): ?string {
		$project_id = (int) ( $project['project_id'] ?? 0 );
		$hash       = (string) ( $project['photo_share_token_hash'] ?? '' );

		if ( $project_id <= 0 || '' === $hash ) {
			return null;
		}

		$stored = get_post_meta( $project_id, self::META_RAW_TOKEN, true );
		if ( is_string( $stored ) && '' !== $stored && InvitationToken::hash( $stored ) === $hash ) {
			return $stored;
		}

		return null;
	}

	public function store_raw_token( int $project_id, string $raw_token ): void {
		if ( $project_id <= 0 || '' === $raw_token ) {
			return;
		}

		update_post_meta( $project_id, self::META_RAW_TOKEN, $raw_token );
	}
}
