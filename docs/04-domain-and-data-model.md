# Domain and data model

**Last verified:** 2026-07-14  
**Schema version:** `Schema::CURRENT_VERSION = 2`

---

## Database tables (`{prefix}pks_oi_*`)

| Table | Primary entity |
|-------|----------------|
| `pks_oi_projects` | Invitation project (1 per order line) |
| `pks_oi_guests` | Guest + RSVP + personal token |
| `pks_oi_address_book` | User-owned reusable contacts |
| `pks_oi_wishlist_items` | Organiser wishlist rows |
| `pks_oi_wishlist_reservations` | Guest reservations |
| `pks_oi_photos` | Guest upload moderation queue |
| `pks_oi_deliveries` | E-mail delivery queue |
| `pks_oi_events` | Audit / lifecycle event log |

All custom tables use UTC datetime columns. Projects link to WooCommerce via `order_id` + `order_item_id` (unique).

---

## Project row (high-signal columns)

| Column | Meaning |
|--------|---------|
| `project_id` | Matches `pks_oi_project` CPT ID |
| `storage_uuid` | Private storage directory name (UUID v4) |
| `status` | `draft`, `active`, `restricted`, `expired`, `deleted`, … |
| `publication_status` | `unpublished` / `published` |
| `envelope_preset`, `background_preset` | Snapshotted at creation (also in `envelope/manifest.json`) |
| `envelope_image_id` | Attachment reference; may be copied to project storage |
| `generic_token_hash` | SHA-256 of opaque generic link token |
| `state_version` | Optimistic locking for design saves |
| `published_version` | Incremented on each publish |
| `published_manifest_path` | Relative path, typically `published/manifest.json` |
| `restricted_at_utc` | Refund / admin restriction |
| `expires_at_utc` | Computed expiry |

---

## Guest row

| Column | Meaning |
|--------|---------|
| `token_hash` | Personal invitation token (hashed) |
| `rsvp_status` | `pending`, `yes`, `no`, `maybe`, … |
| `is_generic_response` | Created from generic-link RSVP |
| `open_count`, `first_opened_at_utc` | Personal open tracking only |

---

## Tokens

`Security\InvitationToken`:

- 32 random bytes → URL-safe base64 (43 chars)
- Store `hash('sha256', $raw)` only
- Personal: `pks_oi_guests.token_hash`
- Generic: `pks_oi_projects.generic_token_hash`
- Resolver checks guest first, then generic (`TokenResolver`)

---

## Project CPT

`pks_oi_project` — admin/support visibility; business data lives in custom tables + private files.

---

## Private file storage

Root: `PKS_OI_STORAGE_PATH` or `{uploads}/pks-oi-private` (fallback).

Per project: `projects/{storage_uuid}/`

| Path | Purpose |
|------|---------|
| `manifest.json` | Editable state manifest |
| `state/current.json` | Canonical builder state JSON |
| `pages/editable/page-NNN.html` | Draft HTML pages |
| `pages/published/page-NNN.html` | Published HTML (checksum verified) |
| `published/manifest.json` | Published page manifest |
| `published/poster-manifest.json` | Poster dimensions + CSS paths |
| `published/poster-display.css` | Snapshotted BPP display CSS |
| `published/poster-fonts.css` | Snapshotted fonts CSS |
| `envelope/manifest.json` | Envelope snapshot |
| `envelope/images/*` | Copied envelope artwork |
| `photos/pending|approved|thumbnails/` | Guest photos |
| `wishlist/images/` | Wishlist item images |

---

## Builder state shape (imported)

Canonical JSON includes at minimum:

- `schema_version`
- `field` — editable field map
- `page` — array of HTML strings
- `size`, `format`
- `product_id`, `template_id`

Import via adapter `load_state` with `mode=import` from order-item storage.

---

## Entitlement model

`Domain\Project\PublicEntitlement` — public access requires:

- Project `active` + `published`
- Not deleted, restricted, or expired
- Valid published manifest
- Guest not archived (personal links)

`ProjectEntitlement` — edit/publish permissions for owners.

---

## Lifecycle statuses

| Transition | Trigger |
|------------|---------|
| Create project | Qualifying order status (`ProjectOrderRegistrar`) |
| Import builder | `ProjectService::import_for_project` |
| Publish | `ProjectPublishService::publish` |
| Restrict | Refund listener |
| Expire | `ExpirationScheduler` |
| Delete | Customer request / retention (`ProjectDeleteService`, hard delete policy) |

See `Domain\Project\ProjectStatus`, `PublicationStatus`.
