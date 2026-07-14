<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Unit\Security;

use PrikOgStreg\OnlineInvitations\Security\InvitationToken;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class InvitationTokenTest extends TestCase {

	public function test_generates_url_safe_token_and_matching_hash(): void {
		$pair = InvitationToken::generate();

		$this->assertNotEmpty( $pair['raw'] );
		$this->assertSame( 43, strlen( $pair['raw'] ) );
		$this->assertTrue( InvitationToken::is_valid_format( $pair['raw'] ) );
		$this->assertSame( InvitationToken::hash( $pair['raw'] ), $pair['hash'] );
		$this->assertSame( 64, strlen( $pair['hash'] ) );
	}

	public function test_rejects_invalid_token_formats(): void {
		$this->assertFalse( InvitationToken::is_valid_format( '' ) );
		$this->assertFalse( InvitationToken::is_valid_format( 'short' ) );
		$this->assertFalse( InvitationToken::is_valid_format( 'has spaces in token' ) );
		$this->assertFalse( InvitationToken::is_valid_format( '<script>alert(1)</script>' ) );
	}
}
