<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin;

use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;

/**
 * Renders the support dashboard on the project CPT edit screen.
 */
final class ProjectSupportScreen {

	public function __construct(
		private ProjectSupportViewModel $view_model,
		private TemplateLoader $templates
	) {}

	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ] );
	}

	public function register_meta_box(): void {
		add_meta_box(
			'pks-oi-project-support',
			__( 'Invitation project support', 'prikogstreg-online-invitations' ),
			[ $this, 'render_meta_box' ],
			ProjectPostType::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * @param \WP_Post $post
	 */
	public function render_meta_box( $post ): void {
		if ( ! $post instanceof \WP_Post || ! current_user_can( Capabilities::SUPPORT ) ) {
			return;
		}

		$view = $this->view_model->build( (int) $post->ID );
		if ( ! is_array( $view ) ) {
			echo '<p>' . esc_html__( 'No linked project row.', 'prikogstreg-online-invitations' ) . '</p>';

			return;
		}

		$this->templates->render( 'admin/support', $view );
	}
}
