<?php
/**
 * Uniform not-found response for unauthorized or missing projects.
 *
 * @package PrikOgStreg\OnlineInvitations
 *
 * @var string $message
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="pks-oi pks-oi-myaccount pks-oi-not-found" role="alert">
	<p><?php echo esc_html( $message ); ?></p>
	<p><a href="<?php echo esc_url( \PrikOgStreg\OnlineInvitations\MyAccount\Endpoints::base_url() ); ?>"><?php esc_html_e( 'Back to online invitations', 'prikogstreg-online-invitations' ); ?></a></p>
</div>
