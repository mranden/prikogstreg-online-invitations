<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\WooCommerce;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\WooCommerce\Cart\CartPayloadValidator;
use PrikOgStreg\OnlineInvitations\WooCommerce\Cart\InvitationCart;
use PrikOgStreg\OnlineInvitations\WooCommerce\Checkout\AccountRequirement;
use PrikOgStreg\OnlineInvitations\WooCommerce\Checkout\CheckoutBlockGuard;
use PrikOgStreg\OnlineInvitations\WooCommerce\Checkout\OrderItemPayload;

/**
 * Registers cart and checkout preservation for online_invitation products.
 */
final class CartCheckoutRegistrar {

	private CartPayloadValidator $validator;

	public function __construct(
		private BuilderService $builder
	) {
		$this->validator = new CartPayloadValidator( $this->builder );
	}

	public function register(): void {
		( new InvitationCart( $this->validator ) )->register();
		( new OrderItemPayload( $this->validator ) )->register();
		( new AccountRequirement() )->register();
		( new CheckoutBlockGuard() )->register();
	}
}
