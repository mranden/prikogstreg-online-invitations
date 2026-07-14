<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin;

use PrikOgStreg\OnlineInvitations\Admin\ProjectPostType;
use PrikOgStreg\OnlineInvitations\Database\Repositories\DeliveryRepository;
use PrikOgStreg\OnlineInvitations\Database\Repositories\ProjectRepository;

/**
 * Shows recent delivery failures on the project CPT screen.
 */
final class DeliveryFailures {

	public function __construct(
		private ProjectRepository $projects,
		private DeliveryRepository $deliveries
	) {}

	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ] );
	}

	public function register_meta_box(): void {
		add_meta_box(
			'pks-oi-delivery-failures',
			__( 'Delivery failures', 'prikogstreg-online-invitations' ),
			[ $this, 'render_meta_box' ],
			ProjectPostType::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * @param \WP_Post $post
	 */
	public function render_meta_box( $post ): void {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$project = $this->projects->find_by_id( (int) $post->ID );
		if ( ! is_array( $project ) ) {
			echo '<p>' . esc_html__( 'No linked project row.', 'prikogstreg-online-invitations' ) . '</p>';

			return;
		}

		$failures = $this->deliveries->list_failures_for_project( (int) $project['project_id'], 10 );
		if ( [] === $failures ) {
			echo '<p>' . esc_html__( 'No failed or skipped deliveries.', 'prikogstreg-online-invitations' ) . '</p>';

			return;
		}

		echo '<ul class="pks-oi-delivery-failures">';
		foreach ( $failures as $row ) {
			printf(
				'<li><strong>%1$s</strong> — %2$s<br /><small>%3$s</small></li>',
				esc_html( (string) ( $row['delivery_type'] ?? '' ) ),
				esc_html( (string) ( $row['status'] ?? '' ) ),
				esc_html( (string) ( $row['last_error_code'] ?? '' ) )
			);
		}
		echo '</ul>';
		echo '<p><small>' . esc_html__( '“Sent” means accepted by wp_mail/mailer, not inbox delivery.', 'prikogstreg-online-invitations' ) . '</small></p>';
	}
}
