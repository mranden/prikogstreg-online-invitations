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

$summary     = $status_summary ?? [];
$items       = $response_list['items'] ?? [];
$pagination  = $response_list ?? [];
$filter_base = Endpoints::project_url( $project_id, ProjectSections::RESPONSES );
$export_args = [
	'pks_oi_export_responses' => '1',
	'pks_oi_project_id'       => $project_id,
];
if ( '' !== (string) ( $status_filter ?? '' ) ) {
	$export_args['pks_oi_rsvp_status'] = $status_filter;
}
$export_url = wp_nonce_url( add_query_arg( $export_args, $filter_base ), ProjectController::NONCE_ACTION );
$filter_pills = [
	'all'                  => __( 'All', 'prikogstreg-online-invitations' ),
	RsvpStatus::ATTENDING  => __( 'Attending', 'prikogstreg-online-invitations' ),
	RsvpStatus::DECLINED   => __( 'Not attending', 'prikogstreg-online-invitations' ),
	RsvpStatus::PENDING    => __( 'Pending', 'prikogstreg-online-invitations' ),
];
$current_filter = '' !== (string) ( $status_filter ?? '' ) ? (string) $status_filter : 'all';
?>
<?php pks_oi_project_open(); ?>
	<?php pks_oi_render_notices( $notices ); ?>
	<?php pks_oi_render_section_nav( $section, $sections, $section_urls ); ?>

	<?php
	pks_oi_section_open(
		'pks-oi-responses-title',
		__( 'Responses', 'prikogstreg-online-invitations' ),
		__( 'Track RSVPs and export guest responses.', 'prikogstreg-online-invitations' )
	);
	?>

	<?php
	pks_oi_render_stats(
		[
			[ 'label' => __( 'Guests', 'prikogstreg-online-invitations' ), 'value' => (string) (int) ( $summary['total'] ?? 0 ) ],
			[ 'label' => __( 'Attending', 'prikogstreg-online-invitations' ), 'value' => (string) (int) ( $summary['attending'] ?? 0 ), 'url' => add_query_arg( 'pks_oi_rsvp_status', RsvpStatus::ATTENDING, $filter_base ) ],
			[ 'label' => __( 'Declined', 'prikogstreg-online-invitations' ), 'value' => (string) (int) ( $summary['declined'] ?? 0 ), 'url' => add_query_arg( 'pks_oi_rsvp_status', RsvpStatus::DECLINED, $filter_base ) ],
			[ 'label' => __( 'Pending', 'prikogstreg-online-invitations' ), 'value' => (string) (int) ( $summary['pending_rsvp'] ?? 0 ), 'url' => add_query_arg( 'pks_oi_rsvp_status', RsvpStatus::PENDING, $filter_base ) ],
		]
	);
	?>

	<div class="pks-oi-section__toolbar">
		<?php pks_oi_render_filter_pills( $filter_base, $filter_pills, $current_filter, 'pks_oi_rsvp_status' ); ?>
		<a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'prikogstreg-online-invitations' ); ?></a>
	</div>

	<?php if ( [] === $items ) : ?>
		<?php
		pks_oi_render_empty_state(
			__( 'No responses yet', 'prikogstreg-online-invitations' ),
			__( 'Responses appear here when guests RSVP to your invitation.', 'prikogstreg-online-invitations' )
		);
		?>
	<?php else : ?>
		<div class="pks-oi-table-wrap">
			<table class="pks-oi-table pks-oi-guest-table">
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
							<td data-label="<?php esc_attr_e( 'Guest', 'prikogstreg-online-invitations' ); ?>">
								<?php echo esc_html( (string) ( $guest['display_name'] ?? '' ) ); ?>
								<?php if ( ! empty( $guest['is_generic_response'] ) ) : ?>
									<?php pks_oi_render_badge( __( 'Generic link', 'prikogstreg-online-invitations' ), 'neutral' ); ?>
								<?php endif; ?>
							</td>
							<td data-label="<?php esc_attr_e( 'RSVP', 'prikogstreg-online-invitations' ); ?>"><?php pks_oi_rsvp_badge( (string) ( $guest['rsvp_status'] ?? '' ) ); ?></td>
							<td data-label="<?php esc_attr_e( 'Attendees', 'prikogstreg-online-invitations' ); ?>"><?php echo esc_html( (string) ( $guest['attendee_count'] ?? '—' ) ); ?></td>
							<td data-label="<?php esc_attr_e( 'Opened', 'prikogstreg-online-invitations' ); ?>"><?php echo esc_html( '' !== (string) ( $guest['first_opened_at_utc'] ?? '' ) ? __( 'Yes', 'prikogstreg-online-invitations' ) : __( 'No', 'prikogstreg-online-invitations' ) ); ?></td>
							<td data-label="<?php esc_attr_e( 'Responded', 'prikogstreg-online-invitations' ); ?>"><?php echo esc_html( pks_oi_format_datetime_display( (string) ( $guest['responded_at_utc'] ?? '' ) ) ?: '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

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
		<?php pks_oi_render_card_open( __( 'Recent changes', 'prikogstreg-online-invitations' ) ); ?>
			<ul class="pks-oi-timeline">
				<?php foreach ( $recent_history as $event ) : ?>
					<li class="pks-oi-timeline__item">
						<strong><?php echo esc_html( (string) ( $event['event_type'] ?? '' ) ); ?></strong>
						<span class="pks-oi-timeline__meta">
							<?php echo esc_html( pks_oi_format_datetime_display( (string) ( $event['created_at_utc'] ?? '' ) ) ); ?>
							<?php if ( ! empty( $event['guest_id'] ) ) : ?>
								— <?php printf( esc_html__( 'Guest #%d', 'prikogstreg-online-invitations' ), (int) $event['guest_id'] ); ?>
							<?php endif; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php pks_oi_render_card_close(); ?>
	<?php endif; ?>

	<?php pks_oi_section_close(); ?>
<?php pks_oi_project_close(); ?>
