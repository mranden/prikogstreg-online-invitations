<?php
/**
 * Admin invitation project detail with tabs.
 *
 * @package PrikOgStreg\OnlineInvitations
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PrikOgStreg\OnlineInvitations\Admin\Invitations\InvitationAdminActions;
use PrikOgStreg\OnlineInvitations\Admin\ProjectAdminListViewModel;
use PrikOgStreg\OnlineInvitations\Admin\ProjectSupportActions;
use PrikOgStreg\OnlineInvitations\Domain\Guest\RsvpStatus;

/** @var array<string, mixed> $project */
/** @var string $tab */
/** @var array<string, array{label:string,url:string,active:bool}> $tabs */
/** @var string $owner_label */
/** @var string $owner_email */
/** @var int $owner_user_id */
/** @var string $back_url */
/** @var bool $can_edit */
/** @var bool $can_moderate */
/** @var bool $can_tools */
/** @var string $draft_preview_url */
/** @var string $published_preview_url */

$project_id = (int) ( $project['project_id'] ?? 0 );
$title      = trim( (string) ( $project['event_title'] ?? '' ) );
if ( '' === $title ) {
	$title = sprintf( __( 'Invitation project #%d', 'prikogstreg-online-invitations' ), $project_id );
}
?>
<h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>
<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action"><?php esc_html_e( 'Back to list', 'prikogstreg-online-invitations' ); ?></a>
<?php if ( '' !== (string) ( $my_account_url ?? '' ) ) : ?>
	<a href="<?php echo esc_url( (string) $my_account_url ); ?>" class="page-title-action" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'My Account', 'prikogstreg-online-invitations' ); ?></a>
<?php endif; ?>
<hr class="wp-header-end" />

<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Project sections', 'prikogstreg-online-invitations' ); ?>">
	<?php foreach ( $tabs as $tab_key => $tab_link ) : ?>
		<a href="<?php echo esc_url( $tab_link['url'] ); ?>" class="nav-tab<?php echo ! empty( $tab_link['active'] ) ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $tab_link['label'] ); ?></a>
	<?php endforeach; ?>
</nav>

<div class="pks-oi-admin-projects__detail-tab">
	<?php
	$partial = 'admin/partials/detail-' . sanitize_key( $tab );
	$partial_file = PKS_OI_PLUGIN_PATH . 'templates/' . $partial . '.php';
	if ( is_readable( $partial_file ) ) {
		include $partial_file;
	} else {
		echo '<p>' . esc_html__( 'This section is not available.', 'prikogstreg-online-invitations' ) . '</p>';
	}
	?>
</div>
