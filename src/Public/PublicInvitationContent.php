<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Public;

/**
 * Structured published invitation content for the public envelope renderer.
 */
final class PublicInvitationContent {

	/**
	 * @param list<array{index:int,html:string}> $pages
	 * @param array{width:int,height:int,orientation:string,size:string,format:string} $poster
	 */
	public function __construct(
		public readonly array $pages,
		public readonly array $poster,
		public readonly int $page_count,
		public readonly ?string $display_css_url = null,
		public readonly ?string $fonts_css_url = null
	) {}

	public function has_multiple_pages(): bool {
		return $this->page_count > 1;
	}

	public function first_page_html(): string {
		return (string) ( $this->pages[0]['html'] ?? '' );
	}
}
