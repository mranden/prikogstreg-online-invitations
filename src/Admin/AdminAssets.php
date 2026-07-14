<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin;

/**
 * Enqueues admin CSS/JS for invitation support screens.
 */
final class AdminAssets {

	public function register(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function enqueue( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! is_object( $screen ) || ProjectPostType::POST_TYPE !== ( $screen->post_type ?? '' ) ) {
			return;
		}

		wp_enqueue_style(
			'pks-oi-admin',
			PKS_OI_PLUGIN_URL . 'assets/build/css/admin.css',
			[],
			PKS_OI_VERSION
		);

		wp_enqueue_script(
			'pks-oi-admin',
			PKS_OI_PLUGIN_URL . 'assets/build/js/admin.js',
			[],
			PKS_OI_VERSION,
			true
		);
	}
}
