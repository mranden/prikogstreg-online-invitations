<?php

declare(strict_types=1);

/**
 * Minimal WC_Product_Simple stub for unit tests.
 */
class WC_Product_Simple {

	protected string $product_type = 'simple';

	public function __construct( $product = 0 ) {}

	public function get_type(): string {
		return $this->product_type;
	}

	public function is_type( string $type ): bool {
		return $this->product_type === $type;
	}
}
