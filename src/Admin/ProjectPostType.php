<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Admin;

/**
 * Private administrative shell for invitation projects.
 */
final class ProjectPostType {

	public const POST_TYPE = 'pks_oi_project';

	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'before_delete_post', [ $this, 'guard_domain_cleanup' ], 10, 2 );
	}

	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'              => [
					'name'          => __( 'Invitation Projects', 'prikogstreg-online-invitations' ),
					'singular_name' => __( 'Invitation Project', 'prikogstreg-online-invitations' ),
				],
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'capability_type'     => 'post',
				'capabilities'        => [
					'edit_post'          => Capabilities::SUPPORT,
					'read_post'          => Capabilities::SUPPORT,
					'delete_post'        => Capabilities::SUPPORT,
					'edit_posts'         => Capabilities::SUPPORT,
					'edit_others_posts'  => Capabilities::SUPPORT,
					'publish_posts'      => Capabilities::SUPPORT,
					'read_private_posts' => Capabilities::SUPPORT,
					'delete_posts'       => Capabilities::SUPPORT,
					'delete_private_posts' => Capabilities::SUPPORT,
					'delete_others_posts'  => Capabilities::SUPPORT,
					'edit_private_posts'   => Capabilities::SUPPORT,
				],
				'map_meta_cap'        => true,
				'supports'            => [ 'title' ],
			]
		);
	}

	/**
	 * Ensures domain rows are removed when the CPT shell is deleted.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function guard_domain_cleanup( int $post_id, $post ): void {
		if ( ! $post instanceof \WP_Post || self::POST_TYPE !== $post->post_type ) {
			return;
		}

		/**
		 * Fires before domain cleanup for a deleted project CPT.
		 *
		 * @param int $project_id CPT post ID.
		 */
		do_action( 'pks_oi_before_project_domain_cleanup', $post_id );
	}
}
