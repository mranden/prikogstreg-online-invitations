<?php
/**
 * Public wishlist section.
 *
 * @package PrikOgStreg\OnlineInvitations
 *
 * @var array<string, mixed> $wishlist
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items = is_array( $wishlist['items'] ?? null ) ? $wishlist['items'] : [];
$external_url = (string) ( $wishlist['external_url'] ?? '' );
$rest_base = (string) ( $wishlist['rest_base'] ?? '' );
$rest_nonce = (string) ( $wishlist['rest_nonce'] ?? '' );
$requires_name = ! empty( $wishlist['requires_name'] );
?>
<div
	class="pks-oi-wishlist"
	data-pks-oi-wishlist
	data-rest-base="<?php echo esc_attr( $rest_base ); ?>"
	data-rest-nonce="<?php echo esc_attr( $rest_nonce ); ?>"
	data-requires-name="<?php echo $requires_name ? '1' : '0'; ?>"
>
	<?php if ( '' !== $external_url ) : ?>
		<p class="pks-oi-wishlist__external">
			<a href="<?php echo esc_url( $external_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View external wishlist', 'prikogstreg-online-invitations' ); ?></a>
		</p>
	<?php endif; ?>

	<?php if ( $requires_name ) : ?>
		<p>
			<label for="pks-oi-wishlist-name"><?php esc_html_e( 'Your name', 'prikogstreg-online-invitations' ); ?></label><br />
			<input type="text" id="pks-oi-wishlist-name" data-pks-oi-wishlist-name />
		</p>
	<?php endif; ?>

	<ul class="pks-oi-wishlist__items">
		<?php foreach ( $items as $item ) : ?>
			<li class="pks-oi-wishlist__item" data-item-id="<?php echo esc_attr( (string) ( $item['wishlist_item_id'] ?? '' ) ); ?>">
				<strong><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></strong>
				<?php if ( '' !== (string) ( $item['description'] ?? '' ) ) : ?>
					<p><?php echo esc_html( (string) $item['description'] ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== (string) ( $item['external_url'] ?? '' ) ) : ?>
					<p><a href="<?php echo esc_url( (string) $item['external_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View product', 'prikogstreg-online-invitations' ); ?></a></p>
				<?php endif; ?>
				<p><?php printf( esc_html__( '%1$d of %2$d reserved', 'prikogstreg-online-invitations' ), (int) ( $item['quantity_reserved'] ?? 0 ), (int) ( $item['quantity_requested'] ?? 0 ) ); ?></p>
				<?php if ( (int) ( $item['quantity_available'] ?? 0 ) > 0 ) : ?>
					<button type="button" class="button" data-pks-oi-wishlist-reserve data-quantity="1"><?php esc_html_e( 'Reserve', 'prikogstreg-online-invitations' ); ?></button>
				<?php endif; ?>
				<?php if ( (int) ( $item['my_reserved_quantity'] ?? 0 ) > 0 ) : ?>
					<button type="button" class="button" data-pks-oi-wishlist-release"><?php esc_html_e( 'Release my reservation', 'prikogstreg-online-invitations' ); ?></button>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<p class="pks-oi-wishlist__status pks-oi-status" data-pks-oi-wishlist-status role="status" aria-live="polite" hidden></p>
</div>
