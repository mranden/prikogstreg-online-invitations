<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\MyAccount;

use PrikOgStreg\OnlineInvitations\MyAccount\Endpoints;
use PrikOgStreg\OnlineInvitations\MyAccount\ProjectSections;
use PrikOgStreg\OnlineInvitations\MyAccount\Router;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class RouterTest extends TestCase {

	private Router $router;

	protected function setUp(): void {
		parent::setUp();
		$this->router = new Router();
	}

	public function test_empty_path_is_list_mode(): void {
		$route = $this->router->parse_request( '' );
		$this->assertSame( 'list', $route['mode'] );
		$this->assertSame( 0, $route['project_id'] );
	}

	public function test_project_id_defaults_to_overview_section(): void {
		$route = $this->router->parse_request( '42' );
		$this->assertSame( 'project', $route['mode'] );
		$this->assertSame( 42, $route['project_id'] );
		$this->assertSame( ProjectSections::OVERVIEW, $route['section'] );
	}

	public function test_project_section_path_is_parsed(): void {
		$route = $this->router->parse_request( '42/design' );
		$this->assertSame( ProjectSections::DESIGN, $route['section'] );
	}

	public function test_invalid_section_falls_back_to_overview(): void {
		$route = $this->router->parse_request( '42/not-real' );
		$this->assertSame( ProjectSections::OVERVIEW, $route['section'] );
	}

	public function test_hidden_address_book_section_redirects_to_guests(): void {
		$route = $this->router->parse_request( '42/address-book' );
		$this->assertSame( ProjectSections::GUESTS, $route['section'] );
	}

	public function test_hidden_publish_section_redirects_to_preview(): void {
		$route = $this->router->parse_request( '42/publish' );
		$this->assertSame( ProjectSections::PREVIEW, $route['section'] );
	}

	public function test_endpoints_build_project_urls(): void {
		$this->assertStringContainsString( 'online-invitations', Endpoints::base_url() );
		$this->assertStringContainsString( '42', Endpoints::project_url( 42 ) );
		$this->assertStringContainsString( 'design', Endpoints::project_url( 42, ProjectSections::DESIGN ) );
	}
}
