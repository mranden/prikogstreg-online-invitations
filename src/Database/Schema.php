<?php

declare(strict_types=1);

namespace PrikOgStreg\OnlineInvitations\Database;

/**
 * Authoritative custom table definitions for dbDelta().
 */
final class Schema {

	public const CURRENT_VERSION = 1;

	/**
	 * @return list<string> Ordered CREATE TABLE statements.
	 */
	public static function get_definitions( string $prefix, string $charset_collate ): array {
		return [
			self::projects( $prefix, $charset_collate ),
			self::guests( $prefix, $charset_collate ),
			self::address_book( $prefix, $charset_collate ),
			self::wishlist_items( $prefix, $charset_collate ),
			self::wishlist_reservations( $prefix, $charset_collate ),
			self::photos( $prefix, $charset_collate ),
			self::deliveries( $prefix, $charset_collate ),
			self::events( $prefix, $charset_collate ),
		];
	}

	public static function projects( string $prefix, string $charset_collate ): string {
		$table = $prefix . 'pks_oi_projects';

		return "CREATE TABLE {$table} (
			project_id bigint(20) unsigned NOT NULL,
			storage_uuid char(36) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			order_id bigint(20) unsigned NOT NULL,
			order_item_id bigint(20) unsigned NOT NULL,
			product_id bigint(20) unsigned NOT NULL,
			template_id varchar(191) NOT NULL,
			status varchar(32) NOT NULL DEFAULT 'draft',
			publication_status varchar(32) NOT NULL DEFAULT 'unpublished',
			locale varchar(20) NOT NULL DEFAULT 'da_DK',
			timezone varchar(64) NOT NULL DEFAULT 'Europe/Copenhagen',
			event_title varchar(255) NULL,
			event_start_utc datetime NULL,
			event_end_utc datetime NULL,
			venue_name varchar(255) NULL,
			venue_address_line1 varchar(255) NULL,
			venue_address_line2 varchar(255) NULL,
			venue_city varchar(128) NULL,
			venue_postcode varchar(32) NULL,
			venue_country char(2) NULL,
			practical_info text NULL,
			organiser_display_name varchar(255) NULL,
			public_contact_email varchar(320) NULL,
			public_contact_phone varchar(64) NULL,
			rsvp_deadline_utc datetime NULL,
			reminder_offset_days smallint(5) unsigned NOT NULL DEFAULT 5,
			guest_photos_enabled tinyint(1) NOT NULL DEFAULT 1,
			internal_wishlist_enabled tinyint(1) NOT NULL DEFAULT 1,
			show_reserver_identity tinyint(1) NOT NULL DEFAULT 0,
			attendee_count_enabled tinyint(1) NOT NULL DEFAULT 1,
			comment_enabled tinyint(1) NOT NULL DEFAULT 1,
			dietary_notes_enabled tinyint(1) NOT NULL DEFAULT 0,
			expires_at_utc datetime NULL,
			expiry_override_utc datetime NULL,
			external_wishlist_url text NULL,
			envelope_preset varchar(64) NOT NULL DEFAULT '',
			background_preset varchar(64) NOT NULL DEFAULT '',
			generic_token_hash char(64) NULL,
			generic_token_version int(10) unsigned NOT NULL DEFAULT 1,
			builder_schema_version varchar(32) NOT NULL DEFAULT '1',
			state_version bigint(20) unsigned NOT NULL DEFAULT 1,
			published_version bigint(20) unsigned NULL,
			state_manifest_path varchar(512) NOT NULL DEFAULT '',
			published_manifest_path varchar(512) NULL,
			last_error_code varchar(100) NULL,
			created_at_utc datetime NOT NULL,
			updated_at_utc datetime NOT NULL,
			published_at_utc datetime NULL,
			restricted_at_utc datetime NULL,
			expired_at_utc datetime NULL,
			deleted_at_utc datetime NULL,
			PRIMARY KEY  (project_id),
			UNIQUE KEY storage_uuid (storage_uuid),
			UNIQUE KEY order_item_id (order_item_id),
			UNIQUE KEY generic_token_hash (generic_token_hash),
			KEY user_status (user_id, status),
			KEY order_id (order_id),
			KEY product_id (product_id),
			KEY publication_lookup (publication_status, status),
			KEY expiry_lookup (expires_at_utc, status)
		) {$charset_collate};";
	}

	public static function guests( string $prefix, string $charset_collate ): string {
		$table = $prefix . 'pks_oi_guests';

		return "CREATE TABLE {$table} (
			guest_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			project_id bigint(20) unsigned NOT NULL,
			address_book_id bigint(20) unsigned NULL,
			display_name varchar(255) NOT NULL,
			email varchar(320) NULL,
			phone varchar(64) NULL,
			party_label varchar(255) NULL,
			token_hash char(64) NOT NULL,
			token_version int(10) unsigned NOT NULL DEFAULT 1,
			rsvp_status varchar(32) NOT NULL DEFAULT 'pending',
			attendee_count smallint(5) unsigned NULL,
			rsvp_comment text NULL,
			dietary_notes text NULL,
			invitation_status varchar(32) NOT NULL DEFAULT 'not_sent',
			is_generic_response tinyint(1) NOT NULL DEFAULT 0,
			first_sent_at_utc datetime NULL,
			last_sent_at_utc datetime NULL,
			first_opened_at_utc datetime NULL,
			last_opened_at_utc datetime NULL,
			open_count int(10) unsigned NOT NULL DEFAULT 0,
			responded_at_utc datetime NULL,
			archived_at_utc datetime NULL,
			created_at_utc datetime NOT NULL,
			updated_at_utc datetime NOT NULL,
			PRIMARY KEY  (guest_id),
			UNIQUE KEY token_hash (token_hash),
			KEY project_archived (project_id, archived_at_utc),
			KEY project_rsvp (project_id, rsvp_status),
			KEY project_invitation (project_id, invitation_status),
			KEY project_email (project_id, email)
		) {$charset_collate};";
	}

	public static function address_book( string $prefix, string $charset_collate ): string {
		$table = $prefix . 'pks_oi_address_book';

		return "CREATE TABLE {$table} (
			address_book_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			display_name varchar(255) NOT NULL,
			email varchar(320) NULL,
			phone varchar(64) NULL,
			notes text NULL,
			normalized_email_hash char(64) NULL,
			created_at_utc datetime NOT NULL,
			updated_at_utc datetime NOT NULL,
			archived_at_utc datetime NULL,
			PRIMARY KEY  (address_book_id),
			KEY user_archived (user_id, archived_at_utc),
			KEY user_email_hash (user_id, normalized_email_hash)
		) {$charset_collate};";
	}

	public static function wishlist_items( string $prefix, string $charset_collate ): string {
		$table = $prefix . 'pks_oi_wishlist_items';

		return "CREATE TABLE {$table} (
			wishlist_item_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			project_id bigint(20) unsigned NOT NULL,
			title varchar(255) NOT NULL,
			description text NULL,
			external_url text NULL,
			image_path varchar(512) NULL,
			quantity_requested smallint(5) unsigned NOT NULL DEFAULT 1,
			quantity_reserved smallint(5) unsigned NOT NULL DEFAULT 0,
			sort_order int(11) NOT NULL DEFAULT 0,
			status varchar(32) NOT NULL DEFAULT 'active',
			created_at_utc datetime NOT NULL,
			updated_at_utc datetime NOT NULL,
			PRIMARY KEY  (wishlist_item_id),
			KEY project_status_sort (project_id, status, sort_order)
		) {$charset_collate};";
	}

	public static function wishlist_reservations( string $prefix, string $charset_collate ): string {
		$table = $prefix . 'pks_oi_wishlist_reservations';

		return "CREATE TABLE {$table} (
			reservation_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			wishlist_item_id bigint(20) unsigned NOT NULL,
			project_id bigint(20) unsigned NOT NULL,
			guest_id bigint(20) unsigned NOT NULL,
			quantity smallint(5) unsigned NOT NULL DEFAULT 1,
			status varchar(32) NOT NULL DEFAULT 'active',
			created_at_utc datetime NOT NULL,
			updated_at_utc datetime NOT NULL,
			released_at_utc datetime NULL,
			PRIMARY KEY  (reservation_id),
			UNIQUE KEY item_guest (wishlist_item_id, guest_id),
			KEY project_status (project_id, status),
			KEY guest_status (guest_id, status)
		) {$charset_collate};";
	}

	public static function photos( string $prefix, string $charset_collate ): string {
		$table = $prefix . 'pks_oi_photos';

		return "CREATE TABLE {$table} (
			photo_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			project_id bigint(20) unsigned NOT NULL,
			guest_id bigint(20) unsigned NULL,
			storage_uuid char(36) NOT NULL,
			relative_path varchar(512) NOT NULL,
			thumbnail_path varchar(512) NULL,
			original_filename varchar(255) NULL,
			mime_type varchar(100) NOT NULL,
			byte_size bigint(20) unsigned NOT NULL,
			width int(10) unsigned NULL,
			height int(10) unsigned NULL,
			sha256 char(64) NOT NULL,
			moderation_status varchar(32) NOT NULL DEFAULT 'pending',
			caption text NULL,
			created_at_utc datetime NOT NULL,
			moderated_at_utc datetime NULL,
			deleted_at_utc datetime NULL,
			PRIMARY KEY  (photo_id),
			UNIQUE KEY storage_path (storage_uuid, relative_path),
			KEY project_moderation (project_id, moderation_status, created_at_utc),
			KEY guest_id (guest_id),
			KEY sha256 (sha256)
		) {$charset_collate};";
	}

	public static function deliveries( string $prefix, string $charset_collate ): string {
		$table = $prefix . 'pks_oi_deliveries';

		return "CREATE TABLE {$table} (
			delivery_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			project_id bigint(20) unsigned NOT NULL,
			guest_id bigint(20) unsigned NULL,
			delivery_type varchar(32) NOT NULL,
			idempotency_key char(64) NOT NULL,
			recipient_hash char(64) NULL,
			status varchar(32) NOT NULL DEFAULT 'queued',
			attempt_count smallint(5) unsigned NOT NULL DEFAULT 0,
			scheduled_at_utc datetime NULL,
			started_at_utc datetime NULL,
			sent_at_utc datetime NULL,
			failed_at_utc datetime NULL,
			last_error_code varchar(100) NULL,
			last_error_message text NULL,
			created_at_utc datetime NOT NULL,
			updated_at_utc datetime NOT NULL,
			PRIMARY KEY  (delivery_id),
			UNIQUE KEY idempotency_key (idempotency_key),
			KEY project_type_status (project_id, delivery_type, status),
			KEY scheduled (scheduled_at_utc, status)
		) {$charset_collate};";
	}

	public static function events( string $prefix, string $charset_collate ): string {
		$table = $prefix . 'pks_oi_events';

		return "CREATE TABLE {$table} (
			event_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			project_id bigint(20) unsigned NOT NULL,
			guest_id bigint(20) unsigned NULL,
			actor_type varchar(32) NOT NULL,
			actor_id bigint(20) unsigned NULL,
			event_type varchar(64) NOT NULL,
			metadata_json longtext NULL,
			created_at_utc datetime NOT NULL,
			PRIMARY KEY  (event_id),
			KEY project_created (project_id, created_at_utc),
			KEY project_type_created (project_id, event_type, created_at_utc),
			KEY guest_created (guest_id, created_at_utc)
		) {$charset_collate};";
	}
}
