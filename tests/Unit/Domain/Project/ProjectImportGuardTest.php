<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\Domain\Project;

use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectImportGuard;
use PrikOgStreg\OnlineInvitations\WooCommerce\Cart\CartPayload;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class ProjectImportGuardTest extends TestCase {

	public function test_build_checksum_state_prefers_order_item_attributes(): void {
		$item = new class() {
			public function get_meta( string $key, bool $single = true ): string {
				return match ( $key ) {
					'pa_bpp_size' => '50-x-70',
					'pa_bpp_format' => 'flat',
					default => '',
				};
			}

			public function get_product_id(): int {
				return 284185;
			}
		};

		$checksum_state = ProjectImportGuard::build_checksum_state(
			[
				'field'      => [ 'uuid-1' => [ 'text' => 'Hello' ] ],
				'page'       => [ '<section>Imported page</section>' ],
				'size'       => 'a5',
				'format'     => 'flat',
				'product_id' => 0,
			],
			$item
		);

		$this->assertSame( '50-x-70', $checksum_state['size'] );
		$this->assertSame( 'flat', $checksum_state['format'] );
		$this->assertSame( 284185, $checksum_state['product_id'] );
	}

	public function test_validate_payload_checksum_allows_drift_when_pages_are_valid(): void {
		$item = new class() {
			public function get_meta( string $key, bool $single = true ): string {
				return match ( $key ) {
					CartPayload::ORDER_META_CHECKSUM => 'deadbeef',
					'pa_bpp_size' => '50-x-70',
					'pa_bpp_format' => 'flat',
					default => '',
				};
			}

			public function get_product_id(): int {
				return 284185;
			}
		};

		$result = ProjectImportGuard::validate_payload_checksum(
			[
				'field'      => [ 'uuid-1' => [ 'text' => 'Hello' ] ],
				'page'       => [ '<section>Imported page</section>' ],
				'size'       => 'a5',
				'format'     => 'flat',
				'product_id' => 284185,
			],
			$item
		);

		$this->assertNull( $result );
	}
}
