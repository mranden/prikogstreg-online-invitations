<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Support;

/**
 * UTC datetime helpers for persistence layers.
 */
final class UtcDateTime {

	public static function now(): string {
		return gmdate( 'Y-m-d H:i:s' );
	}

	public static function format( int $timestamp ): string {
		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}
}
