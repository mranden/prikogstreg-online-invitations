<?php
/**
 * Admin pending photos list.
 *
 * @package PrikOgStreg\OnlineInvitations
 *
 * @var list<array<string,mixed>> $rows
 * @var array<string, mixed>     $pagination
 * @var int                      $page
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PrikOgStreg\OnlineInvitations\Admin\ProjectAdminListViewModel;

$total       = (int) ( $pagination['total'] ?? 0 );
$per_page    = (int) ( $pagination['per_page'] ?? 20 );
$total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;
?>
<?php if ( [] === $rows ) : ?>
	<p><?php esc_html_e( 'No pending photos across invitation projects.', 'prikogstreg-online-invitations' ); ?></p>
<?php else : ?>
	<table class="wp-list-table widefat fixed striped">
		<thead><tr>
			<th><?php esc_html_e( 'Photo', 'prikogstreg-online-invitations' ); ?></th>
			<th><?php esc_html_e( 'Project', 'prikogstreg-online-invitations' ); ?></th>
			<th><?php esc_html_e( 'Uploaded', 'prikogstreg-online-invitations' ); ?></th>
			<th><?php esc_html_e( 'Actions', 'prikogstreg-online-invitations' ); ?></th>
		</tr></thead>
		<tbody>
		<?php foreach ( $rows as $row ) : ?>
			<tr>
				<td><?php echo esc_html( (string) ( $row['original_filename'] ?? '' ) ); ?></td>
				<td><a href="<?php echo esc_url( (string) ( $row['detail_url'] ?? '' ) ); ?>"><?php echo esc_html( (string) ( $row['project_title'] ?? '' ) ); ?></a> <span class="description">#<?php echo esc_html( (string) (int) ( $row['project_id'] ?? 0 ) ); ?></span></td>
				<td><?php echo esc_html( (string) ( $row['created_at_utc'] ?? '' ) ); ?></td>
				<td><a class="button button-small" href="<?php echo esc_url( (string) ( $row['detail_url'] ?? '' ) ); ?>"><?php esc_html_e( 'Moderate', 'prikogstreg-online-invitations' ); ?></a></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav bottom"><div class="tablenav-pages">
			<span class="displaying-num"><?php printf( esc_html( _n( '%d photo', '%d photos', $total, 'prikogstreg-online-invitations' ) ), $total ); ?></span>
			<?php if ( $page > 1 ) : ?><a class="button" href="<?php echo esc_url( add_query_arg( [ 'page' => ProjectAdminListViewModel::PAGE_SLUG . '-photos', 'paged' => $page - 1 ], admin_url( 'admin.php' ) ) ); ?>">‹</a><?php endif; ?>
			<span><?php echo esc_html( (string) $page ); ?> / <?php echo esc_html( (string) $total_pages ); ?></span>
			<?php if ( $page < $total_pages ) : ?><a class="button" href="<?php echo esc_url( add_query_arg( [ 'page' => ProjectAdminListViewModel::PAGE_SLUG . '-photos', 'paged' => $page + 1 ], admin_url( 'admin.php' ) ) ); ?>">›</a><?php endif; ?>
		</div></div>
	<?php endif; ?>
<?php endif; ?>
