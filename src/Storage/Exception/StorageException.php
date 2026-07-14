<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Storage\Exception;

class StorageException extends \RuntimeException {

	public function __construct(
		string $message,
		public readonly string $code_key = 'storage_error',
		?\Throwable $previous = null
	) {
		parent::__construct( $message, 0, $previous );
	}
}
