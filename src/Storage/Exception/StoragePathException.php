<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Storage\Exception;

final class StoragePathException extends StorageException {

	public function __construct( string $message = 'Invalid storage path.' ) {
		parent::__construct( $message, 'invalid_storage_path' );
	}
}
