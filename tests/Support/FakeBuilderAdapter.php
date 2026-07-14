<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Support;

use BPP\Integration\Builder_Adapter_Interface;

/**
 * Configurable PDF Builder adapter double for project lifecycle tests.
 */
final class FakeBuilderAdapter implements Builder_Adapter_Interface {

	/** @var mixed */
	private $load_state_result = null;

	/** @var mixed */
	private $validate_state_result = null;

	/** @var mixed */
	private $save_state_result = null;

	/** @var mixed */
	private $render_public_html_result = '<section>Public page</section>';

	/** @var mixed */
	private $render_preview_html_result = '<section>Preview page</section>';

	/** @var mixed */
	private $render_editor_result = '<div data-bpp-mode="project_edit">Editor</div>';

	public bool $available = true;

	public bool $enqueue_called = false;

	public function is_available(): bool {
		return $this->available;
	}

	public function get_template_id_for_product( int $product_id ): int {
		return $product_id;
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>|object
	 */
	public function load_state( array $context ) {
		if ( is_callable( $this->load_state_result ) ) {
			return ( $this->load_state_result )( $context );
		}

		return $this->load_state_result ?? [
			'field'          => [ 'uuid-1' => [ 'text' => 'Hello' ] ],
			'page'           => [ '<section>Imported page</section>' ],
			'size'           => 'a5',
			'format'         => 'flat',
			'schema_version' => '1',
		];
	}

	/**
	 * @param array<string, mixed> $state
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>|object
	 */
	public function validate_state( array $state, array $context ) {
		if ( is_callable( $this->validate_state_result ) ) {
			return ( $this->validate_state_result )( $state, $context );
		}

		if ( is_object( $this->validate_state_result ) ) {
			return $this->validate_state_result;
		}

		return is_array( $this->validate_state_result ) ? $this->validate_state_result : $state;
	}

	/**
	 * @param array<string, mixed> $state
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>|object|string
	 */
	public function save_state( array $state, array $context ) {
		if ( is_callable( $this->save_state_result ) ) {
			return ( $this->save_state_result )( $state, $context );
		}

		if ( is_object( $this->save_state_result ) ) {
			return $this->save_state_result;
		}

		return is_array( $this->save_state_result ) ? $this->save_state_result : $state;
	}

	/**
	 * @param array<string, mixed> $state
	 * @param array<string, mixed> $context
	 */
	public function render_public_html( array $state, array $context ) {
		if ( is_callable( $this->render_public_html_result ) ) {
			return ( $this->render_public_html_result )( $state, $context );
		}

		return $this->render_public_html_result;
	}

	/**
	 * @param array<string, mixed> $state
	 * @param array<string, mixed> $context
	 */
	public function render_preview_html( array $state, array $context ) {
		if ( is_callable( $this->render_preview_html_result ) ) {
			return ( $this->render_preview_html_result )( $state, $context );
		}

		return $this->render_preview_html_result;
	}

	/**
	 * @param array<string, mixed> $state
	 * @param array<string, mixed> $context
	 */
	public function render_editor( array $state, array $context ) {
		if ( is_callable( $this->render_editor_result ) ) {
			return ( $this->render_editor_result )( $state, $context );
		}

		return $this->render_editor_result;
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public function enqueue_editor_assets( array $context ): void {
		$this->enqueue_called = true;
	}

	public function get_schema_version( array $state ): string {
		return (string) ( $state['schema_version'] ?? '1' );
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	public function create_initial_state( int $product_id, array $context = [] ): array {
		return [
			'schema_version' => '1',
			'field'          => [],
			'page'           => [ '<section class="pks-oi-template-fallback">Default template</section>' ],
			'size'           => 'a5',
			'format'         => 'flat',
			'product_id'     => $product_id,
			'template_id'    => $product_id,
		];
	}

	/**
	 * @param array<string, mixed> $state
	 * @return array<string, mixed>|object
	 */
	public function migrate_state( array $state, string $from, string $to ) {
		return $state;
	}

	/**
	 * @param mixed $result
	 */
	public function with_load_state( $result ): self {
		$this->load_state_result = $result;

		return $this;
	}

	/**
	 * @param mixed $result
	 */
	public function with_validate_state( $result ): self {
		$this->validate_state_result = $result;

		return $this;
	}

	/**
	 * @param mixed $result
	 */
	public function with_save_state( $result ): self {
		$this->save_state_result = $result;

		return $this;
	}

	/**
	 * @param mixed $result
	 */
	public function with_render_public_html( $result ): self {
		$this->render_public_html_result = $result;

		return $this;
	}
}
