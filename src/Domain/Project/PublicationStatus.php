<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

final class PublicationStatus {

	public const UNPUBLISHED = 'unpublished';
	public const PUBLISHED   = 'published';

	/**
	 * @return list<string>
	 */
	public static function all(): array {
		return [ self::UNPUBLISHED, self::PUBLISHED ];
	}

	public static function label( string $status ): string {
		return match ( $status ) {
			self::PUBLISHED   => __( 'Published', 'prikogstreg-online-invitations' ),
			self::UNPUBLISHED => __( 'Unpublished', 'prikogstreg-online-invitations' ),
			default           => $status,
		};
	}
}
