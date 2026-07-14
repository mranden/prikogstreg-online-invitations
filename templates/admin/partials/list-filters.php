<?php
/**
 * Admin list filters for invitation projects.
 *
 * @package PrikOgStreg\OnlineInvitations
 *
 * @var \PrikOgStreg\OnlineInvitations\Admin\Invitations\InvitationAdminQuery $query
 * @var array<string, int>                                                   $counts
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PrikOgStreg\OnlineInvitations\Admin\Invitations\InvitationAdminQuery;
use PrikOgStreg\OnlineInvitations\Admin\ProjectAdminFilter;
use PrikOgStreg\OnlineInvitations\Admin\ProjectAdminListViewModel;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;

?>
<div class="pks-oi-admin-projects__filters">
	<ul class="subsubsub">
		<?php
		$links = [];
		foreach ( ProjectAdminFilter::all() as $status_filter ) {
			$count   = (int) ( $counts[ $status_filter ] ?? 0 );
			$current = $status_filter === $query->filter;
			$filter_query = clone $query;
			$filter_query->filter = $status_filter;
			$filter_query->page = 1;
			$url     = InvitationAdminQuery::list_url( $filter_query );
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

	<div class="alignleft actions">
		<label class="screen-reader-text" for="filter-publication"><?php esc_html_e( 'Publication status', 'prikogstreg-online-invitations' ); ?></label>
		<select name="publication_status" id="filter-publication">
			<option value=""><?php esc_html_e( 'All publication states', 'prikogstreg-online-invitations' ); ?></option>
			<?php foreach ( PublicationStatus::all() as $status ) : ?>
				<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $query->publication_status, $status ); ?>><?php echo esc_html( PublicationStatus::label( $status ) ); ?></option>
			<?php endforeach; ?>
		</select>

		<label class="screen-reader-text" for="filter-event-date"><?php esc_html_e( 'Event date', 'prikogstreg-online-invitations' ); ?></label>
		<select name="event_date" id="filter-event-date">
			<option value=""><?php esc_html_e( 'All event dates', 'prikogstreg-online-invitations' ); ?></option>
			<option value="<?php echo esc_attr( InvitationAdminQuery::EVENT_UPCOMING ); ?>" <?php selected( $query->event_date, InvitationAdminQuery::EVENT_UPCOMING ); ?>><?php esc_html_e( 'Upcoming', 'prikogstreg-online-invitations' ); ?></option>
			<option value="<?php echo esc_attr( InvitationAdminQuery::EVENT_PAST ); ?>" <?php selected( $query->event_date, InvitationAdminQuery::EVENT_PAST ); ?>><?php esc_html_e( 'Past', 'prikogstreg-online-invitations' ); ?></option>
			<option value="<?php echo esc_attr( InvitationAdminQuery::EVENT_NONE ); ?>" <?php selected( $query->event_date, InvitationAdminQuery::EVENT_NONE ); ?>><?php esc_html_e( 'No date', 'prikogstreg-online-invitations' ); ?></option>
		</select>

		<label class="screen-reader-text" for="filter-product"><?php esc_html_e( 'Product ID', 'prikogstreg-online-invitations' ); ?></label>
		<input type="number" name="product_id" id="filter-product" value="<?php echo esc_attr( (string) max( 0, $query->product_id ) ); ?>" min="0" placeholder="<?php esc_attr_e( 'Product ID', 'prikogstreg-online-invitations' ); ?>" />

		<label for="filter-pending-photos">
			<input type="checkbox" name="has_pending_photos" id="filter-pending-photos" value="1" <?php checked( $query->has_pending_photos ); ?> />
			<?php esc_html_e( 'Pending photos only', 'prikogstreg-online-invitations' ); ?>
		</label>

		<?php submit_button( __( 'Filter', 'prikogstreg-online-invitations' ), '', 'filter_action', false ); ?>
	</div>
</div>
