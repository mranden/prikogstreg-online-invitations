<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Storage\Exception;

final class StorageValidationException extends StorageException {

	public function __construct( string $message, string $code_key = 'storage_validation_failed' ) {
		parent::__construct( $message, $code_key );
	}
}
