<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce\Emails;

use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;

/**
 * Shared WooCommerce e-mail behaviour for invitation deliveries.
 */
abstract class AbstractOiEmail extends \WC_Email {

	/** @var array<string, mixed> */
	protected array $delivery = [];

	/** @var array<string, mixed> */
	protected array $context = [];

	protected string $oi_template = '';

	public function __construct() {
		parent::__construct();
	}

	public function get_recipient(): string {
		return (string) $this->recipient;
	}

	public function get_headers(): string {
		$reply = '';
		$project = is_array( $this->context['project'] ?? null ) ? $this->context['project'] : [];
		$contact = sanitize_email( (string) ( $project['public_contact_email'] ?? '' ) );
		if ( '' !== $contact ) {
			$reply = 'Reply-To: ' . $contact . "\r\n";
		}

		return 'Content-Type: text/html; charset=UTF-8' . "\r\n" . $reply;
	}

	public function get_attachments(): array {
		return [];
	}

	/**
	 * @param array<string, mixed> $delivery
	 * @param array<string, mixed> $context
	 */
	public function trigger( $delivery, $context = [] ): void {
		if ( ! $this->is_enabled() || ! is_array( $delivery ) || ! is_array( $context ) ) {
			return;
		}

		$this->delivery = $delivery;
		$this->context  = $context;
		$this->recipient = (string) ( $context['email'] ?? '' );
		if ( '' === $this->recipient ) {
			return;
		}

		$this->placeholders = $this->build_placeholders( $delivery, $context );

		$this->setup_locale();
		$this->send(
			$this->get_recipient(),
			$this->format_string( $this->get_subject() ),
			$this->get_content(),
			$this->get_headers(),
			$this->get_attachments()
		);
		$this->restore_locale();
	}

	public function get_content(): string {
		return $this->get_content_html();
	}

	public function get_content_html(): string {
		$template = ( new TemplateLoader() )->locate( 'emails/' . $this->oi_template );
		if ( '' === $template ) {
			return $this->get_default_html();
		}

		ob_start();
		$delivery = $this->delivery;
		$context  = $this->context;
		include $template;

		return (string) ob_get_clean();
	}

	public function get_content_plain(): string {
		$template = ( new TemplateLoader() )->locate( 'emails/plain/' . $this->oi_template );
		if ( '' === $template ) {
			return wp_strip_all_tags( $this->get_default_html() );
		}

		ob_start();
		$delivery = $this->delivery;
		$context  = $this->context;
		include $template;

		return (string) ob_get_clean();
	}

	protected function get_default_html(): string {
		$project = is_array( $this->context['project'] ?? null ) ? $this->context['project'] : [];
		$title   = (string) ( $project['event_title'] ?? __( 'Your invitation', 'prikogstreg-online-invitations' ) );
		$link    = (string) ( $this->context['invitation_url'] ?? $this->context['account_url'] ?? '' );

		return '<p>' . esc_html( $title ) . '</p><p><a href="' . esc_url( $link ) . '">' . esc_html( $link ) . '</a></p>';
	}

	/**
	 * @param array<string, mixed> $delivery
	 * @param array<string, mixed> $context
	 * @return array<string, string>
	 */
	protected function build_placeholders( array $delivery, array $context ): array {
		$project = is_array( $context['project'] ?? null ) ? $context['project'] : [];
		$guest   = is_array( $context['guest'] ?? null ) ? $context['guest'] : [];

		return [
			'{event_title}'    => (string) ( $project['event_title'] ?? '' ),
			'{organiser_name}' => (string) ( $project['organiser_display_name'] ?? '' ),
			'{guest_name}'     => (string) ( $guest['display_name'] ?? '' ),
			'{account_url}'    => (string) ( $context['account_url'] ?? '' ),
			'{invitation_url}' => (string) ( $context['invitation_url'] ?? '' ),
		];
	}
}
