<?php

declare(strict_types=1);

/**
 * Test double for PDF Builder product loading.
 */
class BPP_Product {

	/** @var array<int, object> */
	private static array $models = [];

	public static function set_test_model( int $product_id, object $model ): void {
		self::$models[ $product_id ] = $model;
	}

	public static function reset_test_models(): void {
		self::$models = [];
	}

	private object $model;

	public function __construct( $id ) {
		$product_id   = (int) $id;
		$this->model  = self::$models[ $product_id ] ?? self::default_model();
	}

	public function __get( string $property ) {
		return $this->model->$property ?? null;
	}

	public function __isset( string $property ): bool {
		return property_exists( $this->model, $property );
	}

	private static function default_model(): object {
		return (object) [
			'active'          => true,
			'type'            => 'invitation',
			'foldable'        => false,
			'default_size'    => 'a5',
			'available_sizes' => [
				'invitation' => [
					[
						'title'            => 'A5',
						'attribute_slug'   => 'a5',
						'available'        => true,
					],
				],
			],
		];
	}
}
