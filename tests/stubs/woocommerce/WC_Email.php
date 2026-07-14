<?php

declare(strict_types=1);

if ( ! class_exists( 'WC_Email', false ) ) {
	/**
	 * Minimal WooCommerce e-mail stub for PHPUnit.
	 */
	class WC_Email {

		public string $id = '';

		public string $title = '';

		public string $description = '';

		public string $subject = '';

		public string $heading = '';

		public string $recipient = '';

		public bool $enabled = true;

		public bool $customer_email = true;

		/** @var array<string, string> */
		public array $placeholders = [];

		public function __construct() {}

		public function is_enabled(): bool {
			return $this->enabled;
		}

		public function get_subject(): string {
			return '';
		}

		public function get_heading(): string {
			return '';
		}

		public function get_content_html(): string {
			return '';
		}

		public function get_content_plain(): string {
			return '';
		}

		public function send( $to, $subject, $message, $headers = '', $attachments = array() ): bool {
			if ( function_exists( 'wp_mail' ) ) {
				return wp_mail( $to, $subject, $message, $headers );
			}

			return true;
		}

		public function setup_locale(): void {}

		public function restore_locale(): void {}

		public function get_blogname(): string {
			return 'Test Store';
		}
	}
}
