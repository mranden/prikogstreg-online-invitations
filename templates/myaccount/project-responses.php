<?php
/**
 * Project responses overview.
 *
 * @package PrikOgStreg\OnlineInvitations
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/_helpers.php';

use PrikOgStreg\OnlineInvitations\Domain\Guest\RsvpStatus;
use PrikOgStreg\OnlineInvitations\MyAccount\Endpoints;
use PrikOgStreg\OnlineInvitations\MyAccount\ProjectController;
use PrikOgStreg\OnlineInvitations\MyAccount\ProjectSections;

$summary    = $status_summary ?? [];
$items      = $response_list['items'] ?? [];
$pagination = $response_list ?? [];
$export_args = [
	'pks_oi_export_responses' => '1',
	'pks_oi_project_id'       => $project_id,
];
if ( '' !== (string) ( $status_filter ?? '' ) ) {
	$export_args['pks_oi_rsvp_status'] = $status_filter;
}
$export_url = wp_nonce_url(
	add_query_arg( $export_args, Endpoints::project_url( $project_id, ProjectSections::RESPONSES ) ),
	ProjectController::NONCE_ACTION
);

$filter_base = Endpoints::project_url( $project_id, ProjectSections::RESPONSES );
?>
<div class="pks-oi pks-oi-myaccount pks-oi-project">
	<?php pks_oi_render_notices( $notices ); ?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<section class="pks-oi-responses" aria-labelledby="pks-oi-responses-title">
		<h3 id="pks-oi-responses-title"><?php esc_html_e( 'Responses', 'prikogstreg-online-invitations' ); ?></h3>
		<p class="pks-oi-responses__summary">
			<?php
			printf(
				esc_html__( '%1$d guests · %2$d attending · %3$d declined · %4$d pending', 'prikogstreg-online-invitations' ),
				(int) ( $summary['total'] ?? 0 ),
				(int) ( $summary['attending'] ?? 0 ),
				(int) ( $summary['declined'] ?? 0 ),
				(int) ( $summary['pending_rsvp'] ?? 0 )
			);
			?>
		</p>

		<form method="get" action="<?php echo esc_url( $filter_base ); ?>" class="pks-oi-responses__filter">
			<label for="pks-oi-rsvp-filter"><?php esc_html_e( 'Filter by status', 'prikogstreg-online-invitations' ); ?></label>
			<select id="pks-oi-rsvp-filter" name="pks_oi_rsvp_status" onchange="this.form.submit()">
				<option value=""><?php esc_html_e( 'All responses', 'prikogstreg-online-invitations' ); ?></option>
				<option value="<?php echo esc_attr( RsvpStatus::ATTENDING ); ?>" <?php selected( $status_filter ?? '', RsvpStatus::ATTENDING ); ?>><?php esc_html_e( 'Attending', 'prikogstreg-online-invitations' ); ?></option>
				<option value="<?php echo esc_attr( RsvpStatus::DECLINED ); ?>" <?php selected( $status_filter ?? '', RsvpStatus::DECLINED ); ?>><?php esc_html_e( 'Not attending', 'prikogstreg-online-invitations' ); ?></option>
				<option value="<?php echo esc_attr( RsvpStatus::PENDING ); ?>" <?php selected( $status_filter ?? '', RsvpStatus::PENDING ); ?>><?php esc_html_e( 'Pending', 'prikogstreg-online-invitations' ); ?></option>
			</select>
		</form>

		<p><a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'prikogstreg-online-invitations' ); ?></a></p>

		<?php if ( [] === $items ) : ?>
			<p><?php esc_html_e( 'No responses match this filter yet.', 'prikogstreg-online-invitations' ); ?></p>
		<?php else : ?>
			<table class="pks-oi-guest-table shop_table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Guest', 'prikogstreg-online-invitations' ); ?></th>
						<th><?php esc_html_e( 'RSVP', 'prikogstreg-online-invitations' ); ?></th>
						<th><?php esc_html_e( 'Attendees', 'prikogstreg-online-invitations' ); ?></th>
						<th><?php esc_html_e( 'Opened', 'prikogstreg-online-invitations' ); ?></th>
						<th><?php esc_html_e( 'Responded', 'prikogstreg-online-invitations' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $items as $guest ) : ?>
						<tr>
							<td>
								<?php echo esc_html( (string) ( $guest['display_name'] ?? '' ) ); ?>
								<?php if ( ! empty( $guest['is_generic_response'] ) ) : ?>
									<span class="pks-oi-badge"><?php esc_html_e( 'Generic link', 'prikogstreg-online-invitations' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( (string) ( $guest['rsvp_status'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $guest['attendee_count'] ?? '—' ) ); ?></td>
							<td><?php echo esc_html( '' !== (string) ( $guest['first_opened_at_utc'] ?? '' ) ? __( 'Yes', 'prikogstreg-online-invitations' ) : __( 'No', 'prikogstreg-online-invitations' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $guest['responded_at_utc'] ?? '—' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( (int) ( $pagination['total'] ?? 0 ) > (int) ( $pagination['per_page'] ?? 20 ) ) : ?>
				<nav class="pks-oi-pagination" aria-label="<?php esc_attr_e( 'Responses pagination', 'prikogstreg-online-invitations' ); ?>">
					<?php
					$current_page = (int) ( $pagination['page'] ?? 1 );
					$total_pages  = (int) ceil( (int) ( $pagination['total'] ?? 0 ) / max( 1, (int) ( $pagination['per_page'] ?? 20 ) ) );
					if ( $current_page > 1 ) :
						?>
						<a href="<?php echo esc_url( add_query_arg( 'pks_oi_response_page', $current_page - 1, $filter_base ) ); ?>"><?php esc_html_e( 'Previous', 'prikogstreg-online-invitations' ); ?></a>
					<?php endif; ?>
					<span><?php printf( esc_html__( 'Page %1$d of %2$d', 'prikogstreg-online-invitations' ), $current_page, max( 1, $total_pages ) ); ?></span>
					<?php if ( $current_page < $total_pages ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'pks_oi_response_page', $current_page + 1, $filter_base ) ); ?>"><?php esc_html_e( 'Next', 'prikogstreg-online-invitations' ); ?></a>
					<?php endif; ?>
				</nav>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( ! empty( $recent_history ) ) : ?>
			<h4><?php esc_html_e( 'Recent changes', 'prikogstreg-online-invitations' ); ?></h4>
			<ul class="pks-oi-response-history">
				<?php foreach ( $recent_history as $event ) : ?>
					<li>
						<strong><?php echo esc_html( (string) ( $event['event_type'] ?? '' ) ); ?></strong>
						— <?php echo esc_html( (string) ( $event['created_at_utc'] ?? '' ) ); ?>
						<?php if ( ! empty( $event['guest_id'] ) ) : ?>
							(<?php printf( esc_html__( 'Guest #%d', 'prikogstreg-online-invitations' ), (int) $event['guest_id'] ); ?>)
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
</div>
