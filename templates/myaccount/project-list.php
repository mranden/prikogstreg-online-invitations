<?php
/**
 * Project list — summary rows only.
 *
 * @package PrikOgStreg\OnlineInvitations
 *
 * @var list<array<string,mixed>> $items
 * @var array<string,mixed>       $pagination
 * @var list<array<string,string>> $notices
 * @var string                    $list_url
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/_helpers.php';
?>
<div class="pks-oi pks-oi-myaccount">
	<header class="pks-oi-header">
		<h2><?php esc_html_e( 'Online invitations', 'prikogstreg-online-invitations' ); ?></h2>
		<p><?php esc_html_e( 'Manage your purchased invitation projects.', 'prikogstreg-online-invitations' ); ?></p>
	</header>

	<?php pks_oi_render_notices( $notices ); ?>

	<?php if ( [] === $items ) : ?>
		<p class="pks-oi-empty"><?php esc_html_e( 'You do not have any invitation projects yet.', 'prikogstreg-online-invitations' ); ?></p>
	<?php else : ?>
		<ul class="pks-oi-project-list">
			<?php foreach ( $items as $item ) : ?>
				<li class="pks-oi-project-list__item">
					<div class="pks-oi-project-list__header">
						<h3 class="pks-oi-project-list__title">
							<a href="<?php echo esc_url( (string) $item['overview_url'] ); ?>">
								<?php echo esc_html( (string) $item['title'] ); ?>
							</a>
						</h3>
					</div>
					<dl class="pks-oi-project-list__meta">
						<div class="pks-oi-project-list__meta-item">
							<dt><?php esc_html_e( 'Status', 'prikogstreg-online-invitations' ); ?></dt>
							<dd>
								<span class="pks-oi-badge pks-oi-badge--<?php echo esc_attr( sanitize_html_class( (string) $item['status'] ) ); ?>">
									<?php echo esc_html( (string) $item['status'] ); ?>
								</span>
							</dd>
						</div>
						<div class="pks-oi-project-list__meta-item">
							<dt><?php esc_html_e( 'Publication', 'prikogstreg-online-invitations' ); ?></dt>
							<dd>
								<span class="pks-oi-badge pks-oi-badge--<?php echo esc_attr( sanitize_html_class( (string) $item['publication_status'] ) ); ?>">
									<?php echo esc_html( (string) $item['publication_status'] ); ?>
								</span>
							</dd>
						</div>
						<?php if ( '' !== (string) $item['event_date'] ) : ?>
							<div class="pks-oi-project-list__meta-item">
								<dt><?php esc_html_e( 'Event date', 'prikogstreg-online-invitations' ); ?></dt>
								<dd><?php echo esc_html( (string) $item['event_date'] ); ?></dd>
							</div>
						<?php endif; ?>
					</dl>
					<div class="pks-oi-project-list__actions">
						<a class="button button-primary" href="<?php echo esc_url( (string) $item['next_action']['url'] ); ?>">
							<?php echo esc_html( (string) $item['next_action']['label'] ); ?>
						</a>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php
		$total_pages = (int) ceil( ( (int) $pagination['total'] ) / max( 1, (int) $pagination['per_page'] ) );
		if ( $total_pages > 1 ) :
			$current = (int) $pagination['page'];
			?>
			<nav class="pks-oi-pagination" aria-label="<?php esc_attr_e( 'Project list pagination', 'prikogstreg-online-invitations' ); ?>">
				<?php if ( $current > 1 ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'pks_oi_page', $current - 1, $list_url ) ); ?>"><?php esc_html_e( 'Previous', 'prikogstreg-online-invitations' ); ?></a>
				<?php endif; ?>
				<span><?php printf( esc_html__( 'Page %1$d of %2$d', 'prikogstreg-online-invitations' ), $current, $total_pages ); ?></span>
				<?php if ( $current < $total_pages ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'pks_oi_page', $current + 1, $list_url ) ); ?>"><?php esc_html_e( 'Next', 'prikogstreg-online-invitations' ); ?></a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>
