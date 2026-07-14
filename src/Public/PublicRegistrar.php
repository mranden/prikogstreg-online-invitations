<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Wishlist\WishlistReservationService;
use PrikOgStreg\OnlineInvitations\Storage\StorageRegistry;
use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;

/**
 * Registers public invitation routes and rendering.
 */
final class PublicRegistrar {

	public function __construct(
		private RepositoryRegistry $repositories,
		private BuilderService $builder,
		private StorageRegistry $storage,
		private TemplateLoader $templates
	) {}

	public function register(): void {
		( new Endpoints() )->register();

		$controller = new PublicController(
			new TokenResolver( $this->repositories->guests(), $this->repositories->projects() ),
			new PublicInvitationLoader( $this->storage->project_storage(), $this->builder ),
			new OpenTracker( $this->repositories->guests() ),
			new InvalidTokenRateLimiter(),
			$this->templates,
			$this->builder,
			new WishlistReservationService(
				$this->repositories->wishlist_items(),
				$this->repositories->wishlist_reservations(),
				$this->repositories->guests(),
				$this->repositories->events()
			)
		);

		$controller->register();
	}
}
