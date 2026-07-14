<?php

declare(strict_types=1);

$project = is_array( $context['project'] ?? null ) ? $context['project'] : [];
$link    = (string) ( $context['invitation_url'] ?? $context['account_url'] ?? '' );
$title   = (string) ( $project['event_title'] ?? '' );
?>
<p><?php echo esc_html( '' !== $title ? $title : __( 'Your invitation', 'prikogstreg-online-invitations' ) ); ?></p>
<?php if ( '' !== $link ) : ?>
<p><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $link ); ?></a></p>
<?php endif; ?>
