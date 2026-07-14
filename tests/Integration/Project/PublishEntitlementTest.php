<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Tests\Integration\Project;

use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectEntitlement;
use PrikOgStreg\OnlineInvitations\Domain\Project\ProjectStatus;
use PrikOgStreg\OnlineInvitations\Tests\TestCase;

final class PublishEntitlementTest extends TestCase {

	public function test_missing_event_title_blocks_publish(): void {
		$project = [
			'status'            => ProjectStatus::ACTIVE,
			'state_version'     => 1,
			'event_title'       => '',
			'event_start_utc'   => '2026-08-01 18:00:00',
		];

		$this->assertFalse( ProjectEntitlement::can_publish_project( $project ) );
	}

	public function test_missing_event_dates_blocks_publish(): void {
		$project = [
			'status'          => ProjectStatus::ACTIVE,
			'state_version'   => 1,
			'event_title'     => 'Summer party',
			'event_start_utc' => '',
			'event_end_utc'   => '',
		];

		$this->assertFalse( ProjectEntitlement::has_required_event_data( $project ) );
		$this->assertFalse( ProjectEntitlement::can_publish_project( $project ) );
	}

	public function test_complete_event_data_allows_publish(): void {
		$project = [
			'status'          => ProjectStatus::ACTIVE,
			'state_version'   => 1,
			'event_title'     => 'Summer party',
			'event_start_utc' => '2026-08-01 18:00:00',
		];

		$this->assertTrue( ProjectEntitlement::can_publish_project( $project ) );
	}

	public function test_restricted_project_cannot_publish(): void {
		$project = [
			'status'          => ProjectStatus::RESTRICTED,
			'state_version'   => 1,
			'event_title'     => 'Summer party',
			'event_start_utc' => '2026-08-01 18:00:00',
		];

		$this->assertFalse( ProjectEntitlement::can_publish_project( $project ) );
	}
}
