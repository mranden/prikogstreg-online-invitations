<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Guest;

use Brain\Monkey\Functions;
use PrikOgStreg\OnlineInvitations\Database\RepositoryRegistry;
use PrikOgStreg\OnlineInvitations\Domain\AddressBook\AddressBookService;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestImportService;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestService;
use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestTokenService;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Domain\Project\PublicationStatus;
use PrikOgStreg\OnlineInvitations\Public\TokenResolver;
use PrikOgStreg\OnlineInvitations\Security\InvitationToken;
use PrikOgStreg\OnlineInvitations\Tests\Support\FakeWpdb;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class GuestManagementTest extends TestCase {

	private FakeWpdb $wpdb;

	private RepositoryRegistry $repositories;

	private GuestService $guests;

	private AddressBookService $address_book;

	private GuestImportService $import;

	protected function setUp(): void {
		parent::setUp();

		$this->wpdb         = new FakeWpdb();
		$this->repositories = new RepositoryRegistry( $this->wpdb );
		$tokens             = new GuestTokenService( $this->repositories->guests() );
		$this->guests       = new GuestService( $this->repositories->guests(), $tokens );
		$this->address_book = new AddressBookService(
			$this->repositories->address_book(),
			$this->repositories->guests(),
			$this->guests,
			$this->repositories->events()
		);
		$this->import = new GuestImportService( $this->repositories->guests(), $this->guests );

		Functions\when( 'do_action' )->justReturn( null );
	}

	public function test_unlimited_guests_can_be_created(): void {
		$project = $this->seed_project( 7 );

		for ( $i = 0; $i < 25; $i++ ) {
			$result = $this->guests->create( $project, [ 'display_name' => 'Guest ' . $i ] );
			$this->assertTrue( $result['success'] ?? false );
		}

		$this->assertSame( 25, $this->repositories->guests()->count_for_project( (int) $project['project_id'] ) );
	}

	public function test_duplicate_email_is_allowed_with_warning(): void {
		$project = $this->seed_project( 7 );
		$this->guests->create( $project, [ 'display_name' => 'Ada', 'email' => 'ada@example.com' ] );
		$second = $this->guests->create( $project, [ 'display_name' => 'Ada Two', 'email' => 'ada@example.com' ] );

		$this->assertTrue( $second['success'] ?? false );
		$this->assertNotEmpty( $second['warnings'] ?? [] );
		$this->assertSame( 2, $this->repositories->guests()->count_for_project( (int) $project['project_id'] ) );
	}

	public function test_archive_revokes_public_token_access(): void {
		$project = $this->seed_project( 7 );
		$created = $this->guests->create( $project, [ 'display_name' => 'Revoke Me' ] );
		$this->assertTrue( $created['success'] ?? false );

		$guest = $this->repositories->guests()->find_by_id_for_project( (int) $created['guest_id'], (int) $project['project_id'] );
		$this->assertIsArray( $guest );

		$this->guests->archive_many( $project, [ (int) $guest['guest_id'] ] );

		$raw_token = $this->raw_token_from_url( (string) $created['invitation_url'] );
		$resolver  = new TokenResolver( $this->repositories->guests(), $this->repositories->projects() );
		$this->assertNull( $resolver->resolve( $raw_token ) );
	}

	public function test_user_cannot_access_other_project_guests(): void {
		$project_a = $this->seed_project( 7, 5001 );
		$project_b = $this->seed_project( 8, 5002 );
		$this->guests->create( $project_a, [ 'display_name' => 'Owner A Guest' ] );

		$foreign = $this->repositories->guests()->find_by_id_for_project( 1, (int) $project_b['project_id'] );
		$this->assertNull( $foreign );
	}

	public function test_import_rejects_too_many_rows(): void {
		$project = $this->seed_project( 7 );
		$lines   = [ 'display_name,email' ];
		for ( $i = 0; $i < 501; $i++ ) {
			$lines[] = 'Guest ' . $i . ',guest' . $i . '@example.com';
		}

		$result = $this->import->preview( $project, implode( "\n", $lines ) );
		$this->assertFalse( $result['success'] ?? true );
		$this->assertSame( 'too_many_rows', $result['error'] ?? null );
	}

	public function test_address_book_snapshot_is_independent_from_contact_updates(): void {
		$project = $this->seed_project( 7 );
		$contact = $this->address_book->create(
			7,
			[
				'display_name' => 'Contact Original',
				'email'        => 'contact@example.com',
				'phone'        => '111',
			]
		);
		$this->assertTrue( $contact['success'] ?? false );

		$this->address_book->add_contacts_to_project( $project, 7, [ (int) $contact['address_book_id'] ] );
		$row = $this->repositories->guests()->list_by_project( (int) $project['project_id'] )[0];
		$this->assertSame( 'Contact Original', $row['display_name'] );

		$stored = $this->repositories->address_book()->find_by_id_for_user( (int) $contact['address_book_id'], 7 );
		$this->assertIsArray( $stored );
		$this->address_book->update( 7, $stored, [ 'display_name' => 'Contact Renamed', 'email' => 'new@example.com' ] );

		$row_after = $this->repositories->guests()->find_by_id_for_project( (int) $row['guest_id'], (int) $project['project_id'] );
		$this->assertSame( 'Contact Original', $row_after['display_name'] ?? '' );
	}

	public function test_user_a_cannot_read_user_b_address_book(): void {
		$this->address_book->create( 7, [ 'display_name' => 'User A Contact' ] );
		$this->assertNull( $this->repositories->address_book()->find_by_id_for_user( 1, 8 ) );
		$this->assertSame( 0, $this->repositories->address_book()->count_for_user( 8 ) );
	}

	public function test_guest_token_hashes_are_unique(): void {
		$project = $this->seed_project( 7 );
		$first   = $this->guests->create( $project, [ 'display_name' => 'One' ] );
		$second  = $this->guests->create( $project, [ 'display_name' => 'Two' ] );

		$guest_a = $this->repositories->guests()->find_by_id_for_project( (int) $first['guest_id'], (int) $project['project_id'] );
		$guest_b = $this->repositories->guests()->find_by_id_for_project( (int) $second['guest_id'], (int) $project['project_id'] );
		$this->assertNotSame( $guest_a['token_hash'], $guest_b['token_hash'] );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function seed_project( int $user_id, int $project_id = 5001 ): array {
		$this->repositories->projects()->insert(
			[
				'project_id'         => $project_id,
				'storage_uuid'       => sprintf( 'eeeeeeee-eeee-4eee-8eee-%012d', $project_id ),
				'user_id'            => $user_id,
				'order_id'           => 100 + $project_id,
				'order_item_id'      => 900 + $project_id,
				'product_id'         => 10,
				'template_id'        => '10',
				'status'             => ProjectStatus::ACTIVE,
				'publication_status' => PublicationStatus::PUBLISHED,
				'state_version'      => 1,
				'generic_token_hash' => InvitationToken::generate()['hash'],
			]
		);

		$project = $this->repositories->projects()->find_by_id( $project_id );
		$this->assertIsArray( $project );

		return $project;
	}

	private function raw_token_from_url( string $url ): string {
		$path = (string) parse_url( $url, PHP_URL_PATH );
		if ( preg_match( '#/invitation/([^/]+)/?#', $path, $matches ) ) {
			return rawurldecode( $matches[1] );
		}

		return '';
	}
}
