<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\Support;

use PrikOgStreg\OnlineInvitations\Support\PosterPreviewHtml;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class PosterPreviewHtmlTest extends TestCase {

	public function test_strips_outer_invitation_wrapper_for_viewport(): void {
		$html = '<div class="bpp-public-invitation" data-bpp-schema-version="1"><div class="bpp-public-page" data-page="0"><div class="bpp-drag-element">Hi</div></div></div>';

		$result = PosterPreviewHtml::prepare_for_viewport( $html );

		$this->assertStringNotContainsString( 'bpp-public-invitation', $result );
		$this->assertStringContainsString( 'pks-oi-poster-page', $result );
		$this->assertStringContainsString( 'bpp-public-page', $result );
		$this->assertStringContainsString( 'bpp-drag-element', $result );
	}

	public function test_wraps_raw_page_markup_in_poster_page(): void {
		$html = '<div class="bpp-public-page" data-page="0"><span>Page</span></div>';

		$result = PosterPreviewHtml::prepare_for_viewport( $html );

		$this->assertSame(
			'<div class="pks-oi-poster-page"><div class="bpp-public-page" data-page="0"><span>Page</span></div></div>',
			$result
		);
	}
}
