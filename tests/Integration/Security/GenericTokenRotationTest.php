<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Security;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\Project\GenericTokenService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;
use PrikOgStreg\OnlineInvitations\Public\TokenResolver;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class GenericTokenRotationTest extends TestCase {

	private FakeWpdb $wpdb;

	private RepositoryRegistry $repositories;

	private GenericTokenService $tokens;

	private TokenResolver $resolver;

	protected function setUp(): void {
		parent::setUp();

		$this->wpdb         = new FakeWpdb();
		$this->repositories = new RepositoryRegistry( $this->wpdb );
		$this->tokens       = new GenericTokenService( $this->repositories->projects() );
		$this->resolver     = new TokenResolver( $this->repositories->guests(), $this->repositories->projects() );

		Functions\when( 'do_action' )->justReturn( null );
	}

	public function test_rotate_invalidates_previous_generic_token(): void {
		$old = InvitationToken::generate();
		$this->seed_project( $old['hash'] );

		$project = $this->repositories->projects()->find_by_id( 8001 );
		$this->assertIsArray( $project );
		$this->assertNotNull( $this->resolver->resolve( $old['raw'] ) );

		$rotated = $this->tokens->rotate( $project );
		$this->assertNotSame( $old['raw'], $rotated['token'] );
		$this->assertSame( 2, $rotated['version'] );

		$this->assertNull( $this->resolver->resolve( $old['raw'] ) );
		$this->assertNotNull( $this->resolver->resolve( $rotated['token'] ) );
	}

	public function test_revoke_makes_generic_token_unresolvable(): void {
		$token = InvitationToken::generate();
		$this->seed_project( $token['hash'] );

		$project = $this->repositories->projects()->find_by_id( 8001 );
		$this->assertIsArray( $project );
		$this->assertNotNull( $this->resolver->resolve( $token['raw'] ) );

		$this->tokens->revoke( $project );
		$this->assertNull( $this->resolver->resolve( $token['raw'] ) );
	}

	private function seed_project( string $generic_hash ): void {
		$this->repositories->projects()->insert(
			[
				'project_id'              => 8001,
				'storage_uuid'            => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
				'user_id'                 => 7,
				'order_id'                => 300,
				'order_item_id'           => 8001,
				'product_id'              => 10,
				'template_id'             => '10',
				'status'                  => ProjectStatus::ACTIVE,
				'publication_status'      => PublicationStatus::PUBLISHED,
				'generic_token_hash'      => $generic_hash,
				'generic_token_version'   => 1,
				'state_version'           => 1,
				'event_title'             => 'Party',
				'event_start_utc'         => '2026-08-01 18:00:00',
			]
		);
	}
}
