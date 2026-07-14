<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Support;

/**
 * Mutable option store for tests without Patchwork conflicts.
 */
final class OptionsStore {

	/** @var array<string, mixed> */
	public static array $values = [];

	public static function reset(): void {
		self::$values = [];
	}

	public static function get( string $key, mixed $default = false ): mixed {
		return self::$values[ $key ] ?? $default;
	}

	public static function set( string $key, mixed $value ): void {
		self::$values[ $key ] = $value;
	}

	public static function delete( string $key ): void {
		unset( self::$values[ $key ] );
	}
}
