<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Support;

use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;

final class FakeWcProduct {

	/** @var array<string, mixed> */
	private array $meta = [];

	public function __construct(
		private int $id,
		private string $type = ProductMeta::TYPE,
		private string $name = 'Invitation Product',
		array $meta = []
	) {
		$this->meta = $meta;
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_name(): string {
		return $this->name;
	}

	public function is_type( string $type ): bool {
		return $this->type === $type;
	}

	public function get_meta( string $key, bool $single = true ) {
		return $this->meta[ $key ] ?? '';
	}
}
