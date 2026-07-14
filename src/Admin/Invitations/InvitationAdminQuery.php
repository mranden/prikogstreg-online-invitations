<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin\Invitations;

use PrikOgStreg\OnlineInvitations\Admin\ProjectAdminFilter;
use PrikOgStreg\OnlineInvitations\Admin\ProjectAdminListViewModel;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;

/**
 * Parsed admin list query parameters.
 */
final class InvitationAdminQuery {

	public const EVENT_UPCOMING = 'upcoming';
	public const EVENT_PAST     = 'past';
	public const EVENT_NONE     = 'none';

	/** @var list<string> */
	private const SORTABLE = [
		'updated_at_utc',
		'created_at_utc',
		'event_start_utc',
		'publication_status',
		'status',
		'order_id',
		'project_id',
	];

	public string $filter = ProjectAdminFilter::ALL;

	public string $search = '';

	public string $publication_status = '';

	public string $event_date = '';

	public string $order_status = '';

	public int $product_id = 0;

	public bool $has_pending_photos = false;

	public string $orderby = 'updated_at_utc';

	public string $order = 'DESC';

	public int $page = 1;

	public int $per_page = 20;

	public static function from_request(): self {
		$query = new self();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query->filter = ProjectAdminFilter::sanitize( (string) ( $_GET['status'] ?? ProjectAdminFilter::ALL ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query->search = sanitize_text_field( wp_unslash( (string) ( $_GET['s'] ?? '' ) ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query->publication_status = sanitize_key( (string) ( $_GET['publication_status'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query->event_date = sanitize_key( (string) ( $_GET['event_date'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query->order_status = sanitize_key( (string) ( $_GET['order_status'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query->product_id = max( 0, (int) ( $_GET['product_id'] ?? 0 ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query->has_pending_photos = ! empty( $_GET['has_pending_photos'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query->orderby = self::sanitize_orderby( (string) ( $_GET['orderby'] ?? 'updated_at_utc' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query->order = self::sanitize_order( (string) ( $_GET['order'] ?? 'desc' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query->page = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page = (int) ( $_GET['per_page'] ?? 0 );
		if ( $per_page > 0 ) {
			$query->per_page = max( 1, min( 100, $per_page ) );
		} else {
			$query->per_page = max( 1, min( 100, (int) get_user_option( 'pks_oi_projects_per_page' ) ?: 20 ) );
		}

		if ( '' !== $query->publication_status && ! in_array( $query->publication_status, PublicationStatus::all(), true ) ) {
			$query->publication_status = '';
		}

		if ( ! in_array( $query->event_date, [ self::EVENT_UPCOMING, self::EVENT_PAST, self::EVENT_NONE, '' ], true ) ) {
			$query->event_date = '';
		}

		return $query;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_repository_args(): array {
		return [
			'filter'              => $this->filter,
			'search'              => $this->search,
			'publication_status'  => $this->publication_status,
			'event_date'          => $this->event_date,
			'order_status'        => $this->order_status,
			'product_id'          => $this->product_id,
			'has_pending_photos'  => $this->has_pending_photos,
			'orderby'             => $this->orderby,
			'order'               => $this->order,
			'page'                => $this->page,
			'per_page'            => $this->per_page,
		];
	}

	/**
	 * @return array<string, string|int>
	 */
	public function query_args(): array {
		$args = [
			'page' => ProjectAdminListViewModel::PAGE_SLUG,
		];

		if ( ProjectAdminFilter::ALL !== $this->filter ) {
			$args['status'] = $this->filter;
		}
		if ( '' !== $this->search ) {
			$args['s'] = $this->search;
		}
		if ( '' !== $this->publication_status ) {
			$args['publication_status'] = $this->publication_status;
		}
		if ( '' !== $this->event_date ) {
			$args['event_date'] = $this->event_date;
		}
		if ( '' !== $this->order_status ) {
			$args['order_status'] = $this->order_status;
		}
		if ( $this->product_id > 0 ) {
			$args['product_id'] = $this->product_id;
		}
		if ( $this->has_pending_photos ) {
			$args['has_pending_photos'] = 1;
		}
		if ( 'updated_at_utc' !== $this->orderby ) {
			$args['orderby'] = $this->orderby;
		}
		if ( 'DESC' !== $this->order ) {
			$args['order'] = strtolower( $this->order );
		}
		if ( $this->page > 1 ) {
			$args['paged'] = $this->page;
		}

		return $args;
	}

	public static function list_url( ?self $query = null ): string {
		$args = null !== $query ? $query->query_args() : [ 'page' => ProjectAdminListViewModel::PAGE_SLUG ];

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	private static function sanitize_orderby( string $orderby ): string {
		$orderby = sanitize_key( $orderby );

		return in_array( $orderby, self::SORTABLE, true ) ? $orderby : 'updated_at_utc';
	}

	private static function sanitize_order( string $order ): string {
		$order = strtoupper( sanitize_key( $order ) );

		return 'ASC' === $order ? 'ASC' : 'DESC';
	}
}
