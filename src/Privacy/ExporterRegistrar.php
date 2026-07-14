<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Privacy;

/**
 * Registers WordPress personal data exporter callback.
 */
final class ExporterRegistrar {

	public function __construct(
		private PersonalDataExporter $exporter
	) {}

	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'register_exporter' ] );
	}

	/**
	 * @param array<string, array<string, mixed>> $exporters
	 * @return array<string, array<string, mixed>>
	 */
	public function register_exporter( array $exporters ): array {
		$exporters[ RetentionPolicy::EXPORTER_ID ] = [
			'exporter_friendly_name' => __( 'Online Invitations', 'prikogstreg-online-invitations' ),
			'callback'               => [ $this, 'export' ],
		];

		return $exporters;
	}

	/**
	 * @param string $email_address
	 * @param int    $page
	 * @return array{data:list<array<string,mixed>>,done:bool}
	 */
	public function export( string $email_address, int $page = 1 ): array {
		unset( $page );

		return [
			'data' => $this->exporter->export_for_email( $email_address ),
			'done' => true,
		];
	}
}
