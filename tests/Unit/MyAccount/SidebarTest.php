<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\MyAccount;

use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\MyAccount\Endpoints;
use PrikOgStreg\OnlineInvitations\MyAccount\ProjectSections;
use PrikOgStreg\OnlineInvitations\MyAccount\Router;
use PrikOgStreg\OnlineInvitations\MyAccount\SectionNavBuilder;
use PrikOgStreg\OnlineInvitations\MyAccount\Sidebar;
use PrikOgStreg\OnlineInvitations\MyAccount\SidebarContext;
use PrikOgStreg\OnlineInvitations\Security\Authorization;
use PrikOgStreg\OnlineInvitations\Support\TemplateLoader;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class SidebarTest extends TestCase {

	private Sidebar $sidebar;

	protected function setUp(): void {
		parent::setUp();

		$registry      = new RepositoryRegistry( new FakeWpdb() );
		$authorization = new Authorization( $registry->projects() );
		$this->sidebar = new Sidebar(
			$authorization,
			new Router(),
			new TemplateLoader(),
			new SectionNavBuilder(
				$registry->guests(),
				$registry->wishlist_items(),
				$registry->photos(),
				$registry->address_book()
			)
		);
	}

	public function test_show_sidebar_returns_false_without_nav_context(): void {
		$this->assertFalse( $this->sidebar->show_sidebar( false, Endpoints::SLUG ) );
	}

	public function test_show_sidebar_returns_true_for_online_invitations_endpoint(): void {
		SidebarContext::set(
			[
				'section'       => ProjectSections::OVERVIEW,
				'sections'      => ProjectSections::labels(),
				'section_urls'  => [],
				'project_id'    => 42,
				'project_title' => 'Test project',
				'list_url'      => '/my-account/online-invitations/',
				'is_support'    => false,
			]
		);

		$this->assertTrue( $this->sidebar->show_sidebar( false, Endpoints::SLUG ) );
	}

	public function test_show_sidebar_leaves_unrelated_endpoints_unchanged(): void {
		SidebarContext::set(
			[
				'section'       => ProjectSections::DESIGN,
				'sections'      => ProjectSections::labels(),
				'section_urls'  => [],
				'project_id'    => 42,
				'project_title' => 'Test project',
				'list_url'      => '/my-account/online-invitations/',
				'is_support'    => false,
			]
		);

		$this->assertFalse( $this->sidebar->show_sidebar( false, 'orders' ) );
	}
}
