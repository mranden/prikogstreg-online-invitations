<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Privacy;

/**
 * Registers WordPress personal data eraser callback.
 */
final class EraserRegistrar {

	public function __construct(
		private PersonalDataEraser $eraser
	) {}

	public function register(): void {
		add_filter( 'wp_privacy_personal_data_erasers', [ $this, 'register_eraser' ] );
	}

	/**
	 * @param array<string, array<string, mixed>> $erasers
	 * @return array<string, array<string, mixed>>
	 */
	public function register_eraser( array $erasers ): array {
		$erasers[ RetentionPolicy::ERASER_ID ] = [
			'eraser_friendly_name' => __( 'Online Invitations', 'prikogstreg-online-invitations' ),
			'callback'             => [ $this, 'erase' ],
		];

		return $erasers;
	}

	/**
	 * @param string $email_address
	 * @param int    $page
	 * @return array{items_removed:bool,items_retained:bool,messages:list<string>,done:bool}
	 */
	public function erase( string $email_address, int $page = 1 ): array {
		unset( $page );

		return $this->eraser->erase_for_email( $email_address );
	}
}
