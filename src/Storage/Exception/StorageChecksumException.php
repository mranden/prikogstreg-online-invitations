<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Storage\Exception;

final class StorageChecksumException extends StorageException {

	public function __construct( string $message = 'Checksum verification failed.' ) {
		parent::__construct( $message, 'checksum_mismatch' );
	}
}
