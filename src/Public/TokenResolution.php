<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

/**
 * Resolved bearer token context — personal guest or generic project link.
 */
final class TokenResolution {

	public const TYPE_PERSONAL = 'personal';
	public const TYPE_GENERIC  = 'generic';

	/**
	 * @param array<string, mixed>      $project
	 * @param array<string, mixed>|null $guest
	 */
	public function __construct(
		private string $type,
		private array $project,
		private ?array $guest
	) {}

	public function type(): string {
		return $this->type;
	}

	public function is_personal(): bool {
		return self::TYPE_PERSONAL === $this->type;
	}

	public function is_generic(): bool {
		return self::TYPE_GENERIC === $this->type;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function project(): array {
		return $this->project;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function guest(): ?array {
		return $this->guest;
	}
}
