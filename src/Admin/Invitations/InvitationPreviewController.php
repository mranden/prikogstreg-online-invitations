<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin\Invitations;

use PrikOgStreg\OnlineInvitations\Admin\Capabilities;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectPreviewService;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;
use PrikOgStreg\OnlineInvitations\Public\PublicInvitationLoader;
use PrikOgStreg\OnlineInvitations\Security\PublishedHtmlSanitizer;

/**
 * Authenticated admin preview endpoint (iframe-safe).
 */
final class InvitationPreviewController {

	public const NONCE_ACTION = 'pks_oi_admin_preview';

	public function __construct(
		private ProjectRepository $projects,
		private ProjectPreviewService $preview,
		private PublicInvitationLoader $published_loader
	) {}

	public function register(): void {
		add_action( 'admin_post_pks_oi_admin_preview', [ $this, 'render' ] );
	}

	public function render(): void {
		if ( ! current_user_can( Capabilities::VIEW ) ) {
			wp_die( esc_html__( 'You do not have permission to preview invitations.', 'prikogstreg-online-invitations' ), '', [ 'response' => 403 ] );
		}

		$project_id = isset( $_GET['project_id'] ) ? (int) $_GET['project_id'] : 0;
		$mode       = sanitize_key( (string) ( $_GET['mode'] ?? 'draft' ) );
		check_admin_referer( self::NONCE_ACTION . '_' . $project_id . '_' . $mode );

		$project = $this->projects->find_by_id( $project_id );
		if ( ! is_array( $project ) ) {
			wp_die( esc_html__( 'Project not found.', 'prikogstreg-online-invitations' ), '', [ 'response' => 404 ] );
		}

		if ( 'published' === $mode && PublicationStatus::PUBLISHED !== (string) ( $project['publication_status'] ?? '' ) ) {
			wp_die( esc_html__( 'Published preview is only available for published projects.', 'prikogstreg-online-invitations' ), '', [ 'response' => 403 ] );
		}

		header( 'X-Robots-Tag: noindex, nofollow', true );
		nocache_headers();

		$html  = $this->render_html( $project, $mode );
		$label = 'published' === $mode
			? __( 'Published preview (admin)', 'prikogstreg-online-invitations' )
			: __( 'Draft preview (admin)', 'prikogstreg-online-invitations' );

		echo '<!DOCTYPE html><html><head><meta charset="utf-8" />';
		echo '<title>' . esc_html( $label ) . '</title>';
		echo '<style>body{margin:0;font-family:system-ui,sans-serif}.pks-oi-admin-preview-banner{background:#1d2327;color:#fff;padding:.5rem 1rem;font-size:.85rem}</style>';
		echo '</head><body>';
		echo '<div class="pks-oi-admin-preview-banner">' . esc_html( $label ) . ' — #' . esc_html( (string) $project_id ) . '</div>';
		if ( '' === $html ) {
			echo '<p style="padding:1rem">' . esc_html__( 'No preview content is available for this project.', 'prikogstreg-online-invitations' ) . '</p>';
		} else {
			echo '<div class="pks-oi-admin-preview-frame">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized published HTML or kses draft.
		}
		echo '</body></html>';
		exit;
	}

	/**
	 * @param array<string, mixed> $project
	 */
	private function render_html( array $project, string $mode ): string {
		if ( 'published' === $mode ) {
			$content = $this->published_loader->load_published_content( $project );
			if ( ! ( $content['success'] ?? false ) ) {
				return '';
			}

			$pages = is_array( $content['pages'] ?? null ) ? $content['pages'] : [];
			$parts = [];
			foreach ( $pages as $page ) {
				$html = (string) ( $page['html'] ?? '' );
				if ( '' !== $html && ! PublishedHtmlSanitizer::contains_blocked_markup( $html ) ) {
					$parts[] = $html;
				}
			}

			return implode( "\n", $parts );
		}

		$result = $this->preview->render_preview( $project );
		$html   = (string) ( $result['html'] ?? '' );
		if ( '' === $html ) {
			return '';
		}

		return PublishedHtmlSanitizer::contains_blocked_markup( $html ) ? wp_kses_post( $html ) : $html;
	}

	public static function preview_url( int $project_id, string $mode = 'draft' ): string {
		$mode = in_array( $mode, [ 'draft', 'published' ], true ) ? $mode : 'draft';

		return wp_nonce_url(
			add_query_arg(
				[
					'action'     => 'pks_oi_admin_preview',
					'project_id' => max( 0, $project_id ),
					'mode'       => $mode,
				],
				admin_url( 'admin-post.php' )
			),
			self::NONCE_ACTION . '_' . $project_id . '_' . $mode
		);
	}
}
