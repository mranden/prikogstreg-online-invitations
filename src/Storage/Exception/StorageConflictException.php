<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Storage\Exception;

final class StorageConflictException extends StorageException {

	public function __construct( string $message = 'Stale state version conflict.' ) {
		parent::__construct( $message, 'state_version_conflict' );
	}
}
