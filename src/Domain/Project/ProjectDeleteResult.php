<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Domain\Project;

/**
 * Result of a hard-delete attempt.
 */
final class ProjectDeleteResult {

	/**
	 * @param list<string> $errors
	 */
	public function __construct(
		public bool $success,
		public bool $done,
		public array $errors = []
	) {}

	/**
	 * @param list<string> $errors
	 */
	public static function completed( array $errors = [] ): self {
		return new self( [] === $errors, true, $errors );
	}

	public static function already_removed(): self {
		return new self( true, true, [] );
	}

	public static function failed( string $error ): self {
		return new self( false, false, [ $error ] );
	}
}
