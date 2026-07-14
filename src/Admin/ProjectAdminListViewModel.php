<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin;

use PrikOgStreg\OnlineInvitations\Admin\Invitations\InvitationAdminQuery;
use PrikOgStreg\OnlineInvitations\Admin\Invitations\InvitationPreviewController;
use PrikOgStreg\OnlineInvitations\Database\Repositories\GuestRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\PhotoRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;
use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;

/**
 * Formats admin list rows for purchased invitation projects.
 */
final class ProjectAdminListViewModel {

	public const PAGE_SLUG = 'pks-online-invitations';

	/** @deprecated Redirected to PAGE_SLUG. */
	public const LEGACY_PAGE_SLUG = 'pks-oi-invitations';

	public function __construct(
		private ProjectRepository $projects,
		private GuestRepository $guests,
		private PhotoRepository $photos,
		private TemplateLoader $templates
	) {}

	/**
	 * @return array<string, mixed>
	 */
	public function build_list( string $filter, int $page ): array {
		$query        = new InvitationAdminQuery();
		$query->filter = ProjectAdminFilter::sanitize( $filter );
		$query->page   = max( 1, $page );

		return $this->build_list_from_query( $query );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function build_list_from_query( InvitationAdminQuery $query ): array {
		$result = $this->projects->list_admin_query( $query->to_repository_args() );

		$project_ids = array_map(
			static fn( array $row ): int => (int) ( $row['project_id'] ?? 0 ),
			$result['items']
		);

		$guest_summaries = $this->guests->batch_status_summaries( $project_ids );
		$photo_counts    = $this->photos->batch_moderation_counts( $project_ids );

		$rows = [];
		foreach ( $result['items'] as $item ) {
			$project_id = (int) ( $item['project_id'] ?? 0 );
			$rows[]     = $this->format_row(
				$item,
				$guest_summaries[ $project_id ] ?? [],
				$photo_counts[ $project_id ] ?? []
			);
		}

		return [
			'filter'     => $query->filter,
			'rows'       => $rows,
			'pagination' => $result,
			'counts'     => $this->filter_counts(),
			'list_url'   => InvitationAdminQuery::list_url( $query ),
			'page_slug'  => self::PAGE_SLUG,
			'query'      => $query,
		];
	}

	/**
	 * @param array<string, int> $counts
	 */
	public function render_filters( InvitationAdminQuery $query, array $counts ): void {
		$this->templates->render(
			'admin/partials/list-filters',
			[
				'query'  => $query,
				'counts' => $counts,
			]
		);
	}

	/**
	 * @return array<string, int>
	 */
	private function filter_counts(): array {
		$counts = [];
		foreach ( ProjectAdminFilter::all() as $filter ) {
			$counts[ $filter ] = $this->projects->count_admin_by_filter( $filter );
		}

		return $counts;
	}

	/**
	 * @param array<string, mixed>       $row
	 * @param array<string, int>           $guest_summary
	 * @param array<string, int>           $photo_counts
	 * @return array<string, mixed>
	 */
	private function format_row( array $row, array $guest_summary, array $photo_counts ): array {
		$project_id = (int) ( $row['project_id'] ?? 0 );
		$user_id    = (int) ( $row['user_id'] ?? 0 );
		$product_id = (int) ( $row['product_id'] ?? 0 );
		$order_id   = (int) ( $row['order_id'] ?? 0 );

		$user = $user_id > 0 && function_exists( 'get_userdata' ) ? get_userdata( $user_id ) : false;
		$product_name = '';
		if ( $product_id > 0 && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( is_object( $product ) && method_exists( $product, 'get_name' ) ) {
				$product_name = (string) $product->get_name();
			}
		}

		$order_url   = '';
		$order_label = '';
		if ( $order_id > 0 && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
			if ( is_object( $order ) && function_exists( 'get_edit_post_link' ) ) {
				$order_url   = (string) get_edit_post_link( $order_id, 'raw' );
				$order_label = function_exists( 'wc_get_order_status_name' )
					? (string) wc_get_order_status_name( $order->get_status() )
					: (string) $order->get_status();
			}
		}

		$title = trim( (string) ( $row['event_title'] ?? '' ) );
		if ( '' === $title ) {
			$title = sprintf(
				/* translators: %d: project ID */
				__( 'Invitation project #%d', 'prikogstreg-online-invitations' ),
				$project_id
			);
		}

		$publication = (string) ( $row['publication_status'] ?? '' );

		return [
			'project_id'          => $project_id,
			'title'               => $title,
			'owner_label'         => is_object( $user ) ? (string) ( $user->display_name ?? $user->user_login ?? '' ) : '',
			'owner_email'         => is_object( $user ) ? (string) ( $user->user_email ?? '' ) : '',
			'owner_user_id'       => $user_id,
			'order_id'            => $order_id,
			'order_url'           => $order_url,
			'order_status_label'  => $order_label,
			'product_id'          => $product_id,
			'product_name'        => $product_name,
			'template_id'         => (string) ( $row['template_id'] ?? '' ),
			'status'              => (string) ( $row['status'] ?? '' ),
			'publication_status'  => $publication,
			'is_published'        => PublicationStatus::PUBLISHED === $publication,
			'event_start_utc'     => (string) ( $row['event_start_utc'] ?? '' ),
			'created_at_utc'      => (string) ( $row['created_at_utc'] ?? '' ),
			'updated_at_utc'      => (string) ( $row['updated_at_utc'] ?? '' ),
			'last_error_code'     => (string) ( $row['last_error_code'] ?? '' ),
			'detail_url'          => self::detail_url( $project_id ),
			'preview_url'         => InvitationPreviewController::preview_url( $project_id, 'draft' ),
			'public_url'          => PublicationStatus::PUBLISHED === $publication
				? InvitationPreviewController::preview_url( $project_id, 'published' )
				: '',
			'guest_total'         => (int) ( $guest_summary['total'] ?? 0 ),
			'guest_attending'     => (int) ( $guest_summary['attending'] ?? 0 ),
			'guest_declined'      => (int) ( $guest_summary['declined'] ?? 0 ),
			'guest_pending'       => (int) ( $guest_summary['pending_rsvp'] ?? 0 ),
			'photo_pending'       => (int) ( $photo_counts['pending'] ?? 0 ),
			'photo_approved'      => (int) ( $photo_counts['approved'] ?? 0 ),
			'photo_total'         => (int) ( $photo_counts['total'] ?? 0 ),
		];
	}

	public static function list_url( string $filter = ProjectAdminFilter::ALL, int $page = 1 ): string {
		$query         = new InvitationAdminQuery();
		$query->filter = ProjectAdminFilter::sanitize( $filter );
		$query->page   = max( 1, $page );

		return InvitationAdminQuery::list_url( $query );
	}

	public static function detail_url( int $project_id, string $tab = 'overview' ): string {
		$args = [
			'page'       => self::PAGE_SLUG,
			'action'     => 'view',
			'project_id' => max( 0, $project_id ),
		];

		if ( '' !== $tab && 'overview' !== $tab ) {
			$args['tab'] = sanitize_key( $tab );
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}
}
