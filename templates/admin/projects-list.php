<?php
/**
 * Admin list of purchased invitation projects.
 *
 * @package PrikOgStreg\OnlineInvitations
 *
 * @var string               $filter
 * @var list<array<string,mixed>> $rows
 * @var array<string, mixed> $pagination
 * @var array<string, int>   $counts
 * @var string               $list_url
 * @var string               $page_slug
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PrikOgStreg\OnlineInvitations\Admin\ProjectAdminFilter;
use PrikOgStreg\OnlineInvitations\Admin\ProjectAdminListViewModel;

$total       = (int) ( $pagination['total'] ?? 0 );
$page        = (int) ( $pagination['page'] ?? 1 );
$per_page    = (int) ( $pagination['per_page'] ?? 20 );
$total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;
?>
<div class="pks-oi-admin-projects__list">
	<ul class="subsubsub">
		<?php
		$links = [];
		foreach ( ProjectAdminFilter::all() as $status_filter ) {
			$count   = (int) ( $counts[ $status_filter ] ?? 0 );
			$current = $status_filter === $filter;
			$url     = ProjectAdminListViewModel::list_url( $status_filter );
			$label   = ProjectAdminFilter::label( $status_filter );
			$links[] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
				esc_url( $url ),
				$current ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				$count
			);
		}
		echo '<li>' . implode( '</li><li>', $links ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
	</ul>

	<?php if ( [] === $rows ) : ?>
		<p class="pks-oi-admin-projects__empty"><?php esc_html_e( 'No invitation projects match this filter.', 'prikogstreg-online-invitations' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped table-view-list pks-oi-admin-projects__table">
			<thead>
				<tr>
					<th scope="col" class="column-primary"><?php esc_html_e( 'Project', 'prikogstreg-online-invitations' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Owner', 'prikogstreg-online-invitations' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Order', 'prikogstreg-online-invitations' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Product', 'prikogstreg-online-invitations' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'prikogstreg-online-invitations' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Updated', 'prikogstreg-online-invitations' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td class="column-primary" data-colname="<?php esc_attr_e( 'Project', 'prikogstreg-online-invitations' ); ?>">
							<strong>
								<a href="<?php echo esc_url( (string) ( $row['detail_url'] ?? '' ) ); ?>">
									<?php echo esc_html( (string) ( $row['title'] ?? '' ) ); ?>
								</a>
							</strong>
							<div class="row-actions">
								<span class="view">
									<a href="<?php echo esc_url( (string) ( $row['detail_url'] ?? '' ) ); ?>"><?php esc_html_e( 'View details', 'prikogstreg-online-invitations' ); ?></a>
								</span>
							</div>
							<button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'prikogstreg-online-invitations' ); ?></span></button>
						</td>
						<td data-colname="<?php esc_attr_e( 'Owner', 'prikogstreg-online-invitations' ); ?>">
							<?php if ( '' !== (string) ( $row['owner_label'] ?? '' ) ) : ?>
								<?php echo esc_html( (string) $row['owner_label'] ); ?><br />
								<span class="description"><?php echo esc_html( (string) ( $row['owner_email'] ?? '' ) ); ?></span>
							<?php else : ?>
								<span class="description">—</span>
							<?php endif; ?>
						</td>
						<td data-colname="<?php esc_attr_e( 'Order', 'prikogstreg-online-invitations' ); ?>">
							<?php if ( '' !== (string) ( $row['order_url'] ?? '' ) ) : ?>
								<a href="<?php echo esc_url( (string) $row['order_url'] ); ?>">#<?php echo esc_html( (string) (int) ( $row['order_id'] ?? 0 ) ); ?></a>
							<?php else : ?>
								#<?php echo esc_html( (string) (int) ( $row['order_id'] ?? 0 ) ); ?>
							<?php endif; ?>
						</td>
						<td data-colname="<?php esc_attr_e( 'Product', 'prikogstreg-online-invitations' ); ?>">
							<?php echo esc_html( (string) ( $row['product_name'] ?? '' ) ); ?>
							<?php if ( (int) ( $row['product_id'] ?? 0 ) > 0 ) : ?>
								<br /><span class="description">#<?php echo esc_html( (string) (int) $row['product_id'] ); ?></span>
							<?php endif; ?>
						</td>
						<td data-colname="<?php esc_attr_e( 'Status', 'prikogstreg-online-invitations' ); ?>">
							<span class="pks-oi-admin-projects__status pks-oi-admin-projects__status--<?php echo esc_attr( (string) ( $row['status'] ?? '' ) ); ?>">
								<?php echo esc_html( (string) ( $row['status'] ?? '' ) ); ?>
							</span>
							<br /><span class="description"><?php echo esc_html( (string) ( $row['publication_status'] ?? '' ) ); ?></span>
							<?php if ( '' !== (string) ( $row['last_error_code'] ?? '' ) ) : ?>
								<br /><code><?php echo esc_html( (string) $row['last_error_code'] ); ?></code>
							<?php endif; ?>
						</td>
						<td data-colname="<?php esc_attr_e( 'Updated', 'prikogstreg-online-invitations' ); ?>">
							<?php echo esc_html( (string) ( $row['updated_at_utc'] ?? '' ) ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<span class="displaying-num">
						<?php
						printf(
							/* translators: %d: number of projects */
							esc_html( _n( '%d project', '%d projects', $total, 'prikogstreg-online-invitations' ) ),
							$total
						);
						?>
					</span>
					<span class="pagination-links">
						<?php if ( $page > 1 ) : ?>
							<a class="prev-page button" href="<?php echo esc_url( ProjectAdminListViewModel::list_url( $filter, $page - 1 ) ); ?>">‹</a>
						<?php endif; ?>
						<span class="paging-input">
							<?php echo esc_html( (string) $page ); ?> / <?php echo esc_html( (string) $total_pages ); ?>
						</span>
						<?php if ( $page < $total_pages ) : ?>
							<a class="next-page button" href="<?php echo esc_url( ProjectAdminListViewModel::list_url( $filter, $page + 1 ) ); ?>">›</a>
						<?php endif; ?>
					</span>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
