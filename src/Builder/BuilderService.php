<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Builder;

/**
 * Discovers the PDF Builder integration adapter.
 *
 * Online Invitations must not call BPP_* classes directly.
 */
final class BuilderService {

	private ?object $adapter = null;

	private bool $resolved = false;

	private bool $available = false;

	public function register(): void {
		add_action( 'init', [ $this, 'resolve' ], 20 );
	}

	public function resolve(): void {
		if ( $this->resolved ) {
			return;
		}

		$this->resolved  = true;
		$this->adapter   = null;
		$this->available = false;

		if ( ! has_filter( 'bpp/integration/service' ) ) {
			return;
		}

		$service = apply_filters( 'bpp/integration/service', null );

		if ( null === $service ) {
			return;
		}

		if ( interface_exists( 'BPP\\Integration\\Builder_Adapter_Interface', false )
			&& $service instanceof \BPP\Integration\Builder_Adapter_Interface ) {
			if ( $service->is_available() ) {
				$this->adapter   = $service;
				$this->available = true;
			}

			return;
		}

		/**
		 * Adapter registered but incompatible — treat as unavailable without fatal.
		 */
		$this->adapter = $service;
	}

	public function is_available(): bool {
		if ( ! $this->resolved ) {
			$this->resolve();
		}

		return $this->available;
	}

	public function get_adapter(): ?object {
		if ( ! $this->resolved ) {
			$this->resolve();
		}

		return $this->available ? $this->adapter : null;
	}

	public function is_integration_registered(): bool {
		return has_filter( 'bpp/integration/service' );
	}

	public function is_integration_incompatible(): bool {
		if ( ! $this->resolved ) {
			$this->resolve();
		}

		return null !== $this->adapter && ! $this->available;
	}
}
