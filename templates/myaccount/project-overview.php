<?php
/**
 * Project overview shell.
 *
 * @package PrikOgStreg\OnlineInvitations
 *
 * @var array<string,mixed>              $project
 * @var int                              $project_id
 * @var string                           $section
 * @var bool                             $is_support
 * @var array<string,string>             $sections
 * @var array<string,string>             $section_urls
 * @var list<array{type:string,message:string}> $notices
 * @var array<string,array<string,mixed>> $checklist
 * @var array{label:string,url:string}   $next_action
 * @var string                           $order_url
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/_helpers.php';
?>
<div class="pks-oi pks-oi-myaccount pks-oi-project">
	<header class="pks-oi-header">
		<h2><?php echo esc_html( (string) ( $project['event_title'] ?? sprintf( __( 'Invitation project #%d', 'prikogstreg-online-invitations' ), $project_id ) ) ); ?></h2>
		<?php if ( $is_support ) : ?>
			<p class="pks-oi-support-banner" role="status"><?php esc_html_e( 'Support view — you are viewing this project as shop staff.', 'prikogstreg-online-invitations' ); ?></p>
		<?php endif; ?>
	</header>

	<?php pks_oi_render_notices( $notices ); ?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<section class="pks-oi-overview" aria-labelledby="pks-oi-overview-title">
		<h3 id="pks-oi-overview-title"><?php esc_html_e( 'Overview', 'prikogstreg-online-invitations' ); ?></h3>

		<dl class="pks-oi-overview__meta">
			<div>
				<dt><?php esc_html_e( 'Project status', 'prikogstreg-online-invitations' ); ?></dt>
				<dd><?php echo esc_html( (string) ( $project['status'] ?? '' ) ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'Publication', 'prikogstreg-online-invitations' ); ?></dt>
				<dd><?php echo esc_html( (string) ( $project['publication_status'] ?? '' ) ); ?></dd>
			</div>
			<?php if ( '' !== (string) ( $project['expires_at_utc'] ?? '' ) ) : ?>
				<div>
					<dt><?php esc_html_e( 'Expires', 'prikogstreg-online-invitations' ); ?></dt>
					<dd><?php echo esc_html( (string) $project['expires_at_utc'] ); ?></dd>
				</div>
			<?php else : ?>
				<div>
					<dt><?php esc_html_e( 'Expires', 'prikogstreg-online-invitations' ); ?></dt>
					<dd><?php esc_html_e( 'Pending event date', 'prikogstreg-online-invitations' ); ?></dd>
				</div>
			<?php endif; ?>
			<?php if ( '' !== $order_url ) : ?>
				<div>
					<dt><?php esc_html_e( 'Order', 'prikogstreg-online-invitations' ); ?></dt>
					<dd><a href="<?php echo esc_url( $order_url ); ?>"><?php printf( esc_html__( 'View order #%d', 'prikogstreg-online-invitations' ), (int) ( $project['order_id'] ?? 0 ) ); ?></a></dd>
				</div>
			<?php endif; ?>
		</dl>

		<h4><?php esc_html_e( 'Setup checklist', 'prikogstreg-online-invitations' ); ?></h4>
		<ul class="pks-oi-checklist">
			<?php foreach ( $checklist as $item ) : ?>
				<li class="<?php echo ! empty( $item['done'] ) ? 'is-done' : 'is-pending'; ?>">
					<strong><?php echo esc_html( (string) $item['label'] ); ?></strong>
					<span><?php echo esc_html( (string) $item['detail'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>

		<p>
			<a class="button button-primary" href="<?php echo esc_url( $next_action['url'] ); ?>">
				<?php echo esc_html( $next_action['label'] ); ?>
			</a>
		</p>
	</section>
</div>
