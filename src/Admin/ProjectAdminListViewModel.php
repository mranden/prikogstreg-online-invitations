<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin;

use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;

/**
 * Formats admin list rows for purchased invitation projects.
 */
final class ProjectAdminListViewModel {

	public const PAGE_SLUG = 'pks-oi-invitations';

	public function __construct(
		private ProjectRepository $projects
	) {}

	/**
	 * @return array<string, mixed>
	 */
	public function build_list( string $filter, int $page ): array {
		$filter = ProjectAdminFilter::sanitize( $filter );
		$result = $this->projects->list_admin_summaries( $filter, $page, 20 );

		$rows = [];
		foreach ( $result['items'] as $item ) {
			$rows[] = $this->format_row( $item );
		}

		return [
			'filter'      => $filter,
			'rows'        => $rows,
			'pagination'  => $result,
			'counts'      => $this->filter_counts(),
			'list_url'    => self::list_url(),
			'page_slug'   => self::PAGE_SLUG,
		];
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
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function format_row( array $row ): array {
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

		$order_url = '';
		if ( $order_id > 0 && function_exists( 'get_edit_post_link' ) ) {
			$order_url = (string) get_edit_post_link( $order_id, 'raw' );
		}

		$title = trim( (string) ( $row['event_title'] ?? '' ) );
		if ( '' === $title ) {
			$title = sprintf(
				/* translators: %d: project ID */
				__( 'Invitation project #%d', 'prikogstreg-online-invitations' ),
				$project_id
			);
		}

		return [
			'project_id'         => $project_id,
			'title'              => $title,
			'owner_label'        => is_object( $user ) ? (string) ( $user->display_name ?? $user->user_login ?? '' ) : '',
			'owner_email'        => is_object( $user ) ? (string) ( $user->user_email ?? '' ) : '',
			'owner_user_id'      => $user_id,
			'order_id'           => $order_id,
			'order_url'          => $order_url,
			'product_id'         => $product_id,
			'product_name'       => $product_name,
			'status'             => (string) ( $row['status'] ?? '' ),
			'publication_status' => (string) ( $row['publication_status'] ?? '' ),
			'event_start_utc'    => (string) ( $row['event_start_utc'] ?? '' ),
			'created_at_utc'     => (string) ( $row['created_at_utc'] ?? '' ),
			'updated_at_utc'     => (string) ( $row['updated_at_utc'] ?? '' ),
			'last_error_code'    => (string) ( $row['last_error_code'] ?? '' ),
			'detail_url'         => self::detail_url( $project_id ),
		];
	}

	public static function list_url( string $filter = ProjectAdminFilter::ALL, int $page = 1 ): string {
		$args = [
			'page' => self::PAGE_SLUG,
		];

		if ( ProjectAdminFilter::ALL !== ProjectAdminFilter::sanitize( $filter ) ) {
			$args['status'] = ProjectAdminFilter::sanitize( $filter );
		}

		if ( $page > 1 ) {
			$args['paged'] = $page;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	public static function detail_url( int $project_id ): string {
		return add_query_arg(
			[
				'page'       => self::PAGE_SLUG,
				'project_id' => max( 0, $project_id ),
			],
			admin_url( 'admin.php' )
		);
	}
}
