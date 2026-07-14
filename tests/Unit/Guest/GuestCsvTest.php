<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\Guest;

use PrikOgStreg\OnlineInvitations\Domain\Guest\GuestCsv;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class GuestCsvTest extends TestCase {

	public function test_neutralizes_formula_injection_cells(): void {
		$this->assertSame( "'=1+1", GuestCsv::neutralize_cell( '=1+1' ) );
		$this->assertSame( "'+cmd", GuestCsv::neutralize_cell( '+cmd' ) );
		$this->assertSame( 'safe', GuestCsv::neutralize_cell( 'safe' ) );
	}

	public function test_export_includes_neutralized_values(): void {
		$csv = GuestCsv::build_export(
			[
				[
					'display_name'       => '=evil',
					'email'              => 'ada@example.com',
					'phone'              => '',
					'party_label'        => '',
					'attendee_count'     => '',
					'rsvp_status'        => 'pending',
					'invitation_status'  => 'not_sent',
					'responded_at'       => '',
				],
			]
		);

		$this->assertStringContainsString( "'=evil", $csv );
	}

	public function test_malformed_import_without_display_name_column_fails(): void {
		$result = GuestCsv::parse_import( "email\nada@example.com\n" );
		$this->assertSame( 'missing_display_name_column', $result['error'] ?? null );
	}

	public function test_import_strips_formula_prefixes(): void {
		$result = GuestCsv::parse_import( "display_name,email\n=Ada,ada@example.com\n" );
		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertSame( 'Ada', $result['rows'][0]['display_name'] );
	}

	public function test_fixture_csv_injection_rows_are_neutralized(): void {
		$path = PKS_OI_PLUGIN_PATH . 'tests/Fixtures/csv-injection-rows.csv';
		$csv  = (string) file_get_contents( $path );
		$result = GuestCsv::parse_import( $csv );

		$this->assertArrayNotHasKey( 'error', $result );
		$name = (string) ( $result['rows'][0]['display_name'] ?? '' );
		$this->assertFalse( str_starts_with( $name, '=' ) );
		$this->assertStringStartsWith( "'", GuestCsv::neutralize_cell( '=cmd' ) );
	}
}
