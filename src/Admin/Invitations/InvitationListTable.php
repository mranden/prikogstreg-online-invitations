<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin\Invitations;

use PrikOgStreg\OnlineInvitations\Admin\Capabilities;
use PrikOgStreg\OnlineInvitations\Admin\ProjectAdminListViewModel;
use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WordPress list table for invitation projects.
 */
final class InvitationListTable extends \WP_List_Table {

	public function __construct(
		private ProjectAdminListViewModel $view_model
	) {
		parent::__construct(
			[
				'singular' => 'invitation-project',
				'plural'   => 'invitation-projects',
				'ajax'     => false,
			]
		);
	}

	public function get_columns(): array {
		return [
			'invitation'  => __( 'Invitation', 'prikogstreg-online-invitations' ),
			'customer'    => __( 'Customer', 'prikogstreg-online-invitations' ),
			'order'       => __( 'Order', 'prikogstreg-online-invitations' ),
			'product'     => __( 'Product', 'prikogstreg-online-invitations' ),
			'event_date'  => __( 'Event date', 'prikogstreg-online-invitations' ),
			'status'      => __( 'Status', 'prikogstreg-online-invitations' ),
			'guests'      => __( 'Guests', 'prikogstreg-online-invitations' ),
			'photos'      => __( 'Photos', 'prikogstreg-online-invitations' ),
			'updated'     => __( 'Updated', 'prikogstreg-online-invitations' ),
		];
	}

	/**
	 * @return array<string, array<int, string>>
	 */
	protected function get_sortable_columns(): array {
		return [
			'invitation' => [ 'event_title', false ],
			'event_date' => [ 'event_start_utc', false ],
			'status'     => [ 'status', false ],
			'updated'    => [ 'updated_at_utc', true ],
			'order'      => [ 'order_id', false ],
		];
	}

	protected function get_default_primary_column_name(): string {
		return 'invitation';
	}

	public function no_items(): void {
		esc_html_e( 'No invitation projects match your search or filter.', 'prikogstreg-online-invitations' );
	}

	public function prepare_items(): void {
		$query = InvitationAdminQuery::from_request();

		$this->_column_headers = [
			$this->get_columns(),
			[],
			$this->get_sortable_columns(),
			$this->get_default_primary_column_name(),
		];

		$context       = $this->view_model->build_list_from_query( $query );
		$this->items   = $context['rows'];
		$this->query   = $query;
		$this->counts  = $context['counts'];

		$this->set_pagination_args(
			[
				'total_items' => (int) ( $context['pagination']['total'] ?? 0 ),
				'per_page'    => $query->per_page,
				'total_pages' => max( 1, (int) ceil( ( (int) ( $context['pagination']['total'] ?? 0 ) ) / max( 1, $query->per_page ) ) ),
			]
		);
	}

	/** @var InvitationAdminQuery|null */
	private ?InvitationAdminQuery $query = null;

	/** @var array<string, int> */
	private array $counts = [];

	/**
	 * @param array<string, mixed> $item
	 */
	public function column_default( $item, $column_name ): string {
		return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
	}

	/**
	 * @param array<string, mixed> $item
	 */
	protected function column_invitation( $item ): string {
		$title      = (string) ( $item['title'] ?? '' );
		$detail_url = (string) ( $item['detail_url'] ?? '' );
		$project_id = (int) ( $item['project_id'] ?? 0 );

		$output  = '<strong><a class="row-title" href="' . esc_url( $detail_url ) . '">' . esc_html( $title ) . '</a></strong>';
		$output .= '<div class="row-id"><span class="description">#' . esc_html( (string) $project_id ) . '</span></div>';
		$output .= $this->row_actions( $this->row_action_links( $item ) );

		return $output;
	}

	/**
	 * @param array<string, mixed> $item
	 * @return array<string, string>
	 */
	private function row_action_links( array $item ): array {
		$detail_url = (string) ( $item['detail_url'] ?? '' );
		$links      = [
			'view' => '<a href="' . esc_url( $detail_url ) . '">' . esc_html__( 'View', 'prikogstreg-online-invitations' ) . '</a>',
		];

		if ( current_user_can( Capabilities::EDIT ) ) {
			$links['edit'] = '<a href="' . esc_url( add_query_arg( [ 'tab' => 'event' ], $detail_url ) ) . '">' . esc_html__( 'Edit support fields', 'prikogstreg-online-invitations' ) . '</a>';
		}

		if ( current_user_can( Capabilities::VIEW ) ) {
			$preview_url = (string) ( $item['preview_url'] ?? '' );
			if ( '' !== $preview_url ) {
				$links['preview'] = '<a href="' . esc_url( $preview_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Preview', 'prikogstreg-online-invitations' ) . '</a>';
			}
		}

		if ( '' !== (string) ( $item['public_url'] ?? '' ) && ( $item['is_published'] ?? false ) ) {
			$links['public'] = '<a href="' . esc_url( (string) $item['public_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open public invitation', 'prikogstreg-online-invitations' ) . '</a>';
		}

		if ( '' !== (string) ( $item['order_url'] ?? '' ) ) {
			$links['order'] = '<a href="' . esc_url( (string) $item['order_url'] ) . '">' . esc_html__( 'Open order', 'prikogstreg-online-invitations' ) . '</a>';
		}

		if ( (int) ( $item['owner_user_id'] ?? 0 ) > 0 ) {
			$user_url = get_edit_user_link( (int) $item['owner_user_id'] );
			if ( is_string( $user_url ) && '' !== $user_url ) {
				$links['customer'] = '<a href="' . esc_url( $user_url ) . '">' . esc_html__( 'Open customer', 'prikogstreg-online-invitations' ) . '</a>';
			}
		}

		if ( (int) ( $item['photo_pending'] ?? 0 ) > 0 && current_user_can( Capabilities::MODERATE_PHOTOS ) ) {
			$links['photos'] = '<a href="' . esc_url( add_query_arg( [ 'tab' => 'photos' ], $detail_url ) ) . '">' . esc_html__( 'Open photos', 'prikogstreg-online-invitations' ) . '</a>';
		}

		return $links;
	}

	/**
	 * @param array<string, mixed> $item
	 */
	protected function column_customer( $item ): string {
		if ( '' === (string) ( $item['owner_label'] ?? '' ) ) {
			return '<span class="description">—</span>';
		}

		return esc_html( (string) $item['owner_label'] ) . '<br /><span class="description">' . esc_html( (string) ( $item['owner_email'] ?? '' ) ) . '</span>';
	}

	/**
	 * @param array<string, mixed> $item
	 */
	protected function column_order( $item ): string {
		$order_id  = (int) ( $item['order_id'] ?? 0 );
		$order_url = (string) ( $item['order_url'] ?? '' );
		$status    = (string) ( $item['order_status_label'] ?? '' );

		$label = '#' . $order_id;
		if ( '' !== $order_url ) {
			$label = '<a href="' . esc_url( $order_url ) . '">#' . esc_html( (string) $order_id ) . '</a>';
		}

		if ( '' !== $status ) {
			$label .= '<br /><span class="description">' . esc_html( $status ) . '</span>';
		}

		return $label;
	}

	/**
	 * @param array<string, mixed> $item
	 */
	protected function column_product( $item ): string {
		$name = esc_html( (string) ( $item['product_name'] ?? '' ) );
		$id   = (int) ( $item['product_id'] ?? 0 );
		$tpl  = (string) ( $item['template_id'] ?? '' );

		$output = $name;
		if ( $id > 0 ) {
			$output .= '<br /><span class="description">#' . esc_html( (string) $id );
			if ( '' !== $tpl ) {
				$output .= ' · ' . esc_html( $tpl );
			}
			$output .= '</span>';
		}

		return $output;
	}

	/**
	 * @param array<string, mixed> $item
	 */
	protected function column_event_date( $item ): string {
		$start = (string) ( $item['event_start_utc'] ?? '' );

		return '' !== $start ? esc_html( $start ) : '<span class="description">—</span>';
	}

	/**
	 * @param array<string, mixed> $item
	 */
	protected function column_status( $item ): string {
		$status      = esc_html( (string) ( $item['status'] ?? '' ) );
		$publication = esc_html( (string) ( $item['publication_status'] ?? '' ) );
		$error       = (string) ( $item['last_error_code'] ?? '' );

		$output = '<span class="pks-oi-admin-projects__status pks-oi-admin-projects__status--' . esc_attr( (string) ( $item['status'] ?? '' ) ) . '">' . $status . '</span>';
		$output .= '<br /><span class="description">' . $publication . '</span>';
		if ( '' !== $error ) {
			$output .= '<br /><code>' . esc_html( $error ) . '</code>';
		}

		return $output;
	}

	/**
	 * @param array<string, mixed> $item
	 */
	protected function column_guests( $item ): string {
		return sprintf(
			/* translators: 1: total guests, 2: attending, 3: declined, 4: pending */
			esc_html__( '%1$d · %2$d / %3$d / %4$d', 'prikogstreg-online-invitations' ),
			(int) ( $item['guest_total'] ?? 0 ),
			(int) ( $item['guest_attending'] ?? 0 ),
			(int) ( $item['guest_declined'] ?? 0 ),
			(int) ( $item['guest_pending'] ?? 0 )
		);
	}

	/**
	 * @param array<string, mixed> $item
	 */
	protected function column_photos( $item ): string {
		return sprintf(
			/* translators: 1: pending photos, 2: approved photos, 3: total photos */
			esc_html__( '%1$d / %2$d / %3$d', 'prikogstreg-online-invitations' ),
			(int) ( $item['photo_pending'] ?? 0 ),
			(int) ( $item['photo_approved'] ?? 0 ),
			(int) ( $item['photo_total'] ?? 0 )
		);
	}

	/**
	 * @param array<string, mixed> $item
	 */
	protected function column_updated( $item ): string {
		return esc_html( (string) ( $item['updated_at_utc'] ?? '' ) );
	}

	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which || null === $this->query ) {
			return;
		}

		$this->view_model->render_filters( $this->query, $this->counts );
	}
}
