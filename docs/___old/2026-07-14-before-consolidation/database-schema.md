# Database schema — Prikogstreg Online Invitations

**Status:** Authoritative for V1 implementation (implemented in `src/Database/Schema.php`)  
**Schema version option:** `pks_oi_db_version` (integer, starts at `1`)  
**Collation:** Site WordPress collation (`$wpdb->get_charset_collate()`)  
**Engine:** InnoDB via `dbDelta()`

---

## Migration order

Migrations run in this order on activation and bootstrap:

| Order | Object | Reason |
|------:|--------|--------|
| 1 | `pks_oi_projects` | Parent table; FK targets from all others |
| 2 | `pks_oi_guests` | Depends on `project_id` |
| 3 | `pks_oi_address_book` | Independent of projects; used before guest import |
| 4 | `pks_oi_wishlist_items` | Depends on `project_id` |
| 5 | `pks_oi_wishlist_reservations` | Depends on wishlist item + guest |
| 6 | `pks_oi_photos` | Depends on `project_id`, optional `guest_id` |
| 7 | `pks_oi_deliveries` | Depends on `project_id`, optional `guest_id` |
| 8 | `pks_oi_events` | Audit log; depends on `project_id` |

No foreign-key constraints are enforced at the database layer (WordPress convention). Repositories enforce referential integrity in application code.

---

## Table: `{$wpdb->prefix}pks_oi_projects`

One row per invitation project. `project_id` equals the private CPT post ID (`pks_oi_project`).

```sql
CREATE TABLE {$wpdb->prefix}pks_oi_projects (
  project_id BIGINT(20) UNSIGNED NOT NULL,
  storage_uuid CHAR(36) NOT NULL,
  user_id BIGINT(20) UNSIGNED NOT NULL,
  order_id BIGINT(20) UNSIGNED NOT NULL,
  order_item_id BIGINT(20) UNSIGNED NOT NULL,
  product_id BIGINT(20) UNSIGNED NOT NULL,
  template_id VARCHAR(191) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'draft',
  publication_status VARCHAR(32) NOT NULL DEFAULT 'unpublished',
  locale VARCHAR(20) NOT NULL DEFAULT 'da_DK',
  timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Copenhagen',
  event_title VARCHAR(255) NULL,
  event_start_utc DATETIME NULL,
  event_end_utc DATETIME NULL,
  venue_name VARCHAR(255) NULL,
  venue_address_line1 VARCHAR(255) NULL,
  venue_address_line2 VARCHAR(255) NULL,
  venue_city VARCHAR(128) NULL,
  venue_postcode VARCHAR(32) NULL,
  venue_country CHAR(2) NULL,
  practical_info TEXT NULL,
  organiser_display_name VARCHAR(255) NULL,
  public_contact_email VARCHAR(320) NULL,
  public_contact_phone VARCHAR(64) NULL,
  rsvp_deadline_utc DATETIME NULL,
  reminder_offset_days SMALLINT(5) UNSIGNED NOT NULL DEFAULT 5,
  guest_photos_enabled TINYINT(1) NOT NULL DEFAULT 1,
  internal_wishlist_enabled TINYINT(1) NOT NULL DEFAULT 1,
  show_reserver_identity TINYINT(1) NOT NULL DEFAULT 0,
  attendee_count_enabled TINYINT(1) NOT NULL DEFAULT 1,
  comment_enabled TINYINT(1) NOT NULL DEFAULT 1,
  dietary_notes_enabled TINYINT(1) NOT NULL DEFAULT 0,
  expires_at_utc DATETIME NULL,
  expiry_override_utc DATETIME NULL,
  external_wishlist_url TEXT NULL,
  envelope_preset VARCHAR(64) NOT NULL DEFAULT '',
  background_preset VARCHAR(64) NOT NULL DEFAULT '',
  generic_token_hash CHAR(64) NULL,
  generic_token_version INT(10) UNSIGNED NOT NULL DEFAULT 1,
  builder_schema_version VARCHAR(32) NOT NULL DEFAULT '1',
  state_version BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
  published_version BIGINT(20) UNSIGNED NULL,
  state_manifest_path VARCHAR(512) NOT NULL DEFAULT '',
  published_manifest_path VARCHAR(512) NULL,
  last_error_code VARCHAR(100) NULL,
  created_at_utc DATETIME NOT NULL,
  updated_at_utc DATETIME NOT NULL,
  published_at_utc DATETIME NULL,
  restricted_at_utc DATETIME NULL,
  expired_at_utc DATETIME NULL,
  deleted_at_utc DATETIME NULL,
  PRIMARY KEY (project_id),
  UNIQUE KEY storage_uuid (storage_uuid),
  UNIQUE KEY order_item_id (order_item_id),
  UNIQUE KEY generic_token_hash (generic_token_hash),
  KEY user_status (user_id, status),
  KEY order_id (order_id),
  KEY product_id (product_id),
  KEY publication_lookup (publication_status, status),
  KEY expiry_lookup (expires_at_utc, status)
) {$charset_collate};
```

**Notes**

- No raw tokens, HTML, or builder JSON in this table.
- `order_item_id` unique enforces one project per qualifying order line.
- Event-detail columns live here for V1 (structured text only; no arbitrary HTML).
- `show_reserver_identity` defaults to `0` (gift-surprise privacy).

---

## Table: `{$wpdb->prefix}pks_oi_guests`

Unlimited guests per project in V1.

```sql
CREATE TABLE {$wpdb->prefix}pks_oi_guests (
  guest_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id BIGINT(20) UNSIGNED NOT NULL,
  address_book_id BIGINT(20) UNSIGNED NULL,
  display_name VARCHAR(255) NOT NULL,
  email VARCHAR(320) NULL,
  phone VARCHAR(64) NULL,
  party_label VARCHAR(255) NULL,
  token_hash CHAR(64) NOT NULL,
  token_version INT(10) UNSIGNED NOT NULL DEFAULT 1,
  rsvp_status VARCHAR(32) NOT NULL DEFAULT 'pending',
  attendee_count SMALLINT(5) UNSIGNED NULL,
  rsvp_comment TEXT NULL,
  dietary_notes TEXT NULL,
  invitation_status VARCHAR(32) NOT NULL DEFAULT 'not_sent',
  is_generic_response TINYINT(1) NOT NULL DEFAULT 0,
  first_sent_at_utc DATETIME NULL,
  last_sent_at_utc DATETIME NULL,
  first_opened_at_utc DATETIME NULL,
  last_opened_at_utc DATETIME NULL,
  open_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
  responded_at_utc DATETIME NULL,
  archived_at_utc DATETIME NULL,
  created_at_utc DATETIME NOT NULL,
  updated_at_utc DATETIME NOT NULL,
  PRIMARY KEY (guest_id),
  UNIQUE KEY token_hash (token_hash),
  KEY project_archived (project_id, archived_at_utc),
  KEY project_rsvp (project_id, rsvp_status),
  KEY project_invitation (project_id, invitation_status),
  KEY project_email (project_id, email)
) {$charset_collate};
```

**Notes**

- No unique `(project_id, email)` — households and duplicate e-mails are allowed.
- `is_generic_response` distinguishes guests created via the generic social link.
- `phone` is organiser reference only; no SMS in V1.

---

## Table: `{$wpdb->prefix}pks_oi_address_book`

Private to one WordPress customer (`user_id`).

```sql
CREATE TABLE {$wpdb->prefix}pks_oi_address_book (
  address_book_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT(20) UNSIGNED NOT NULL,
  display_name VARCHAR(255) NOT NULL,
  email VARCHAR(320) NULL,
  phone VARCHAR(64) NULL,
  notes TEXT NULL,
  normalized_email_hash CHAR(64) NULL,
  created_at_utc DATETIME NOT NULL,
  updated_at_utc DATETIME NOT NULL,
  archived_at_utc DATETIME NULL,
  PRIMARY KEY (address_book_id),
  KEY user_archived (user_id, archived_at_utc),
  KEY user_email_hash (user_id, normalized_email_hash)
) {$charset_collate};
```

---

## Table: `{$wpdb->prefix}pks_oi_wishlist_items`

```sql
CREATE TABLE {$wpdb->prefix}pks_oi_wishlist_items (
  wishlist_item_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id BIGINT(20) UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  external_url TEXT NULL,
  image_path VARCHAR(512) NULL,
  quantity_requested SMALLINT(5) UNSIGNED NOT NULL DEFAULT 1,
  quantity_reserved SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
  sort_order INT(11) NOT NULL DEFAULT 0,
  status VARCHAR(32) NOT NULL DEFAULT 'active',
  created_at_utc DATETIME NOT NULL,
  updated_at_utc DATETIME NOT NULL,
  PRIMARY KEY (wishlist_item_id),
  KEY project_status_sort (project_id, status, sort_order)
) {$charset_collate};
```

---

## Table: `{$wpdb->prefix}pks_oi_wishlist_reservations`

```sql
CREATE TABLE {$wpdb->prefix}pks_oi_wishlist_reservations (
  reservation_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  wishlist_item_id BIGINT(20) UNSIGNED NOT NULL,
  project_id BIGINT(20) UNSIGNED NOT NULL,
  guest_id BIGINT(20) UNSIGNED NOT NULL,
  quantity SMALLINT(5) UNSIGNED NOT NULL DEFAULT 1,
  status VARCHAR(32) NOT NULL DEFAULT 'active',
  created_at_utc DATETIME NOT NULL,
  updated_at_utc DATETIME NOT NULL,
  released_at_utc DATETIME NULL,
  PRIMARY KEY (reservation_id),
  UNIQUE KEY item_guest (wishlist_item_id, guest_id),
  KEY project_status (project_id, status),
  KEY guest_status (guest_id, status)
) {$charset_collate};
```

**Atomicity:** Reservation services use a transaction:

1. `SELECT ... FOR UPDATE` on `pks_oi_wishlist_items` row.
2. Verify `quantity_reserved + requested <= quantity_requested`.
3. Upsert reservation; update `quantity_reserved` in same transaction.

---

## Table: `{$wpdb->prefix}pks_oi_photos`

```sql
CREATE TABLE {$wpdb->prefix}pks_oi_photos (
  photo_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id BIGINT(20) UNSIGNED NOT NULL,
  guest_id BIGINT(20) UNSIGNED NULL,
  storage_uuid CHAR(36) NOT NULL,
  relative_path VARCHAR(512) NOT NULL,
  thumbnail_path VARCHAR(512) NULL,
  original_filename VARCHAR(255) NULL,
  mime_type VARCHAR(100) NOT NULL,
  byte_size BIGINT(20) UNSIGNED NOT NULL,
  width INT(10) UNSIGNED NULL,
  height INT(10) UNSIGNED NULL,
  sha256 CHAR(64) NOT NULL,
  moderation_status VARCHAR(32) NOT NULL DEFAULT 'pending',
  caption TEXT NULL,
  created_at_utc DATETIME NOT NULL,
  moderated_at_utc DATETIME NULL,
  deleted_at_utc DATETIME NULL,
  PRIMARY KEY (photo_id),
  UNIQUE KEY storage_path (storage_uuid, relative_path),
  KEY project_moderation (project_id, moderation_status, created_at_utc),
  KEY guest_id (guest_id),
  KEY sha256 (sha256)
) {$charset_collate};
```

---

## Table: `{$wpdb->prefix}pks_oi_deliveries`

```sql
CREATE TABLE {$wpdb->prefix}pks_oi_deliveries (
  delivery_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id BIGINT(20) UNSIGNED NOT NULL,
  guest_id BIGINT(20) UNSIGNED NULL,
  delivery_type VARCHAR(32) NOT NULL,
  idempotency_key CHAR(64) NOT NULL,
  recipient_hash CHAR(64) NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'queued',
  attempt_count SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
  scheduled_at_utc DATETIME NULL,
  started_at_utc DATETIME NULL,
  sent_at_utc DATETIME NULL,
  failed_at_utc DATETIME NULL,
  last_error_code VARCHAR(100) NULL,
  last_error_message TEXT NULL,
  created_at_utc DATETIME NOT NULL,
  updated_at_utc DATETIME NOT NULL,
  PRIMARY KEY (delivery_id),
  UNIQUE KEY idempotency_key (idempotency_key),
  KEY project_type_status (project_id, delivery_type, status),
  KEY scheduled (scheduled_at_utc, status)
) {$charset_collate};
```

**`delivery_type` values:** `welcome`, `demo`, `guest_invitation`, `rsvp_reminder`, `rsvp_confirmation`, `organizer_rsvp`, `photo_notification`.

---

## Table: `{$wpdb->prefix}pks_oi_events`

Append-only audit log.

```sql
CREATE TABLE {$wpdb->prefix}pks_oi_events (
  event_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id BIGINT(20) UNSIGNED NOT NULL,
  guest_id BIGINT(20) UNSIGNED NULL,
  actor_type VARCHAR(32) NOT NULL,
  actor_id BIGINT(20) UNSIGNED NULL,
  event_type VARCHAR(64) NOT NULL,
  metadata_json LONGTEXT NULL,
  created_at_utc DATETIME NOT NULL,
  PRIMARY KEY (event_id),
  KEY project_created (project_id, created_at_utc),
  KEY project_type_created (project_id, event_type, created_at_utc),
  KEY guest_created (guest_id, created_at_utc)
) {$charset_collate};
```

**Metadata limits:** Max 8 KB JSON; no tokens, raw HTML, or full builder payloads.

---

## WooCommerce order-item meta (written by Online Invitations)

| Meta key | Type | Purpose |
|----------|------|---------|
| `_pks_oi_project_id` | int | Link order item → project |
| `_pks_oi_imported_at` | datetime UTC | Import audit |

Existing PDF Builder meta (`_bpp_custom_data_file`, `pa_bpp_size`, `pa_bpp_format`, `_pdf_files`) remains unchanged.

---

## Product meta (Online Invitations)

Stored via WooCommerce product CRUD on `online_invitation` products:

| Meta key | Type | Purpose |
|----------|------|---------|
| `_pks_oi_envelope_preset` | string slug | Envelope animation preset |
| `_pks_oi_background_preset` | string slug | Generic background preset |
| `_pks_oi_default_locale` | string | Default project locale |
| `_pks_oi_reminder_offset_days` | int | Default RSVP reminder offset (5) |
| `_pks_oi_guest_photos_default` | bool | Default guest photo uploads |
| `_pks_oi_wishlist_default` | bool | Default internal wishlist |

Builder template remains in PDF Builder `_bpp_product` on the same product ID.
