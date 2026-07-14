<?php
/**
 * My Account project sidebar navigation.
 *
 * @package PrikOgStreg\OnlineInvitations
 *
 * @var string                              $section
 * @var array<string,string>                $sections
 * @var array<string,string>                $section_urls
 * @var string                              $list_url
 * @var string                              $project_title
 * @var array{completed:int,total:int,percent:int} $progress
 * @var list<array{slug:string,label:string,items:list<array<string,mixed>>}> $groups
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/_helpers.php';

$progress = is_array( $progress ?? null ) ? $progress : [ 'completed' => 0, 'total' => 0, 'percent' => 0 ];
$groups   = is_array( $groups ?? null ) ? $groups : [];
?>
<div class="pks-oi-sidebar">
	<p class="pks-oi-sidebar__back">
		<a class="pks-oi-sidebar__back-link" href="<?php echo esc_url( $list_url ); ?>">
			<span class="pks-oi-sidebar__back-icon" aria-hidden="true">←</span>
			<?php esc_html_e( 'All invitations', 'prikogstreg-online-invitations' ); ?>
		</a>
	</p>

	<?php if ( '' !== (string) ( $project_title ?? '' ) ) : ?>
		<h2 class="pks-oi-sidebar__title"><?php echo esc_html( (string) $project_title ); ?></h2>
	<?php endif; ?>

	<?php if ( (int) ( $progress['total'] ?? 0 ) > 0 ) : ?>
		<div
			class="pks-oi-sidebar__progress"
			role="progressbar"
			aria-valuemin="0"
			aria-valuemax="<?php echo esc_attr( (string) (int) ( $progress['total'] ?? 0 ) ); ?>"
			aria-valuenow="<?php echo esc_attr( (string) (int) ( $progress['completed'] ?? 0 ) ); ?>"
			aria-label="<?php echo esc_attr__( 'Setup progress', 'prikogstreg-online-invitations' ); ?>"
		>
			<p class="pks-oi-sidebar__progress-label">
				<?php
				printf(
					/* translators: 1: completed steps, 2: total steps */
					esc_html__( '%1$d of %2$d ready', 'prikogstreg-online-invitations' ),
					(int) ( $progress['completed'] ?? 0 ),
					(int) ( $progress['total'] ?? 0 )
				);
				?>
			</p>
			<div class="pks-oi-sidebar__progress-track">
				<span class="pks-oi-sidebar__progress-fill" style="width: <?php echo esc_attr( (string) max( 0, min( 100, (int) ( $progress['percent'] ?? 0 ) ) ) ); ?>%;"></span>
			</div>
		</div>
	<?php endif; ?>

	<?php pks_oi_render_section_nav_groups( $groups ); ?>
</div>
