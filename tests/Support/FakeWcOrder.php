<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Support;

use PrikOgStreg\OnlineInvitations\WooCommerce\Cart\CartPayload;
use PrikOgStreg\OnlineInvitations\WooCommerce\ProductType\ProductMeta;

final class FakeWcOrderItem {

	/** @var array<string, mixed> */
	private array $meta = [];

	public function __construct(
		private int $id,
		private int $product_id,
		private object $product,
		bool $invitation = true
	) {
		if ( $invitation ) {
			$this->meta[ CartPayload::ORDER_META_TYPE ] = ProductMeta::TYPE;
		}
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_product_id(): int {
		return $this->product_id;
	}

	public function get_product(): object {
		return $this->product;
	}

	public function get_meta( string $key, bool $single = true ) {
		return $this->meta[ $key ] ?? '';
	}

	public function update_meta_data( string $key, $value ): void {
		$this->meta[ $key ] = $value;
	}

	public function save(): void {}
}

final class FakeWcOrder {

	/** @var array<int, FakeWcOrderItem> */
	private array $items;

	public function __construct(
		private int $id,
		private int $customer_id,
		private string $status,
		array $items
	) {
		$this->items = $items;
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_customer_id(): int {
		return $this->customer_id;
	}

	public function get_status(): string {
		return $this->status;
	}

	/**
	 * @return array<int, FakeWcOrderItem>
	 */
	public function get_items( string $type = 'line_item' ): array {
		return $this->items[ $type ] ?? [];
	}

	public function get_formatted_billing_full_name(): string {
		return 'Test Customer';
	}
}
