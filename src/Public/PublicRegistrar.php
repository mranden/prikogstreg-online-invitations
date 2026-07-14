<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

use PrikOgStreg\OnlineInvitations\Builder\BuilderService;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoAccessCodeService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoAccessRateLimiter;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoGuestSessionService;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoServiceFactory;
use PrikOgStreg\OnlineInvitations\Domain\Photo\PhotoShareTokenService;
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

		$project_storage = $this->storage->project_storage();
		$poster_assets   = new PosterDisplayAssets( $project_storage );

		$photo_share_tokens = new PhotoShareTokenService( $this->repositories->projects() );

		$controller = new PublicController(
			new TokenResolver( $this->repositories->guests(), $this->repositories->projects() ),
			new PublicInvitationLoader( $project_storage, $this->builder, $poster_assets ),
			new OpenTracker( $this->repositories->guests() ),
			new InvalidTokenRateLimiter(),
			$this->templates,
			$this->builder,
			new WishlistReservationService(
				$this->repositories->wishlist_items(),
				$this->repositories->wishlist_reservations(),
				$this->repositories->guests(),
				$this->repositories->events()
			),
			$project_storage,
			$this->storage->file_streams(),
			new EnvelopeImageResolver( $project_storage ),
			$poster_assets,
			$photo_share_tokens
		);

		$controller->register();

		$photo_service = PhotoServiceFactory::create( $this->repositories, $this->storage );
		( new PhotoShareEndpoints() )->register();
		PhotoShareEndpoints::maybe_flush_rewrites();
		( new PhotoSharePublicController(
			$photo_share_tokens,
			new PhotoGuestSessionService(),
			$photo_service,
			new InvalidTokenRateLimiter(),
			$this->storage->file_streams(),
			$this->templates
		) )->register();

		( new PublicAssetManager() )->register();
	}
}
