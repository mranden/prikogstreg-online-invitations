# Privacy and retention — Prikogstreg Online Invitations

**Status:** Implemented (V1 technical defaults)  
**Date:** 2026-07-14  
**Note:** Legal/business retention periods require stakeholder confirmation where marked.

---

## 1. Personal data categories

| Category | Data subjects | Examples | Location |
|----------|---------------|----------|----------|
| Account | Customer | WP user, billing e-mail | WooCommerce |
| Project metadata | Customer | Event title, dates, venue, organiser name | `pks_oi_projects` |
| Builder content | Customer | Names, dates, photos in invitation | Project files |
| Guests | Customer + guests | Names, e-mails, RSVP, dietary notes | `pks_oi_guests` |
| Address book | Customer | Reusable contacts | `pks_oi_address_book` |
| Wishlist | Customer + guests | Gift preferences, reservations | `pks_oi_wishlist_*` |
| Photos | Guest + customer | Uploaded event images | `pks_oi_photos` + files |
| Delivery logs | Customer + guests | Send status, hashed recipient | `pks_oi_deliveries` |
| Audit events | System | Actor, event type, safe metadata | `pks_oi_events` |
| Tokens | Guests | **Hashes only** | projects/guests tables |
| Rate limits | Visitors | Hashed IP, short TTL | Transients |

---

## 2. WordPress privacy tools (implemented)

### Policy (`src/Privacy/Policy.php`)

Registers suggested privacy policy sections via `wp_add_privacy_policy_content` covering guest data, photo uploads, e-mail delivery, retention, archive/delete, external wishlist links, and rate-limit minimization.

### Exporter (`src/Privacy/PersonalDataExporter.php`)

Registered as `pks-oi-online-invitations` via `wp_privacy_personal_data_exporters`.

| Group | Scope |
|-------|-------|
| Projects | Owner's project metadata (no token hashes or manifest paths) |
| Address book | Owner contacts |
| Guests | Owner export of project guests |
| Wishlist / photos / deliveries / events | Owner project scope |
| Guest self | Guest e-mail match only — **no other guests on same project** |

### Eraser (`src/Privacy/PersonalDataEraser.php`)

Registered as `pks-oi-online-invitations` via `wp_privacy_personal_data_erasers`.

| Subject | Behavior |
|---------|----------|
| Customer (WP user e-mail) | Delete address book; hard-delete owned projects; report WooCommerce order retention |
| Guest (e-mail match) | Anonymize guest row (`GuestAnonymizer`); revoke token; delete guest photos |
| Idempotent | Second run reports already removed |

**Legal preservation:** WooCommerce order records and order-item meta are **not** silently deleted. Erasure messages document this boundary.

---

## 3. Customer lifecycle (My Account → Settings)

| Action | Service | Effect |
|--------|---------|--------|
| Archive project | `ProjectArchiveService` | `status=archived`, unpublish, cancel queued jobs + AS hooks |
| Restore from archive | `ProjectArchiveService` | `archived → active` |
| Delete permanently | `ProjectCustomerDeleteService` | Requires typing `DELETE`; calls `ProjectHardDeleteService` |

**Expiry ≠ deletion:** Expired projects remain until archive or delete.

---

## 4. Admin hard delete

`ProjectHardDeleteService` (support UI or privacy eraser):

1. Cancel all queued deliveries and `pks-oi` Action Scheduler jobs for the project
2. Delete private storage tree (reports `storage_delete_failed` on partial failure)
3. Delete CPT → `ProjectDomainCleanup` removes custom tables
4. Audit `project.deleted` with reason/source
5. Idempotent — repeat calls return `done=true`

---

## 5. Retention matrix

| Data | V1 technical retention | After expiry | After erasure request |
|------|------------------------|--------------|----------------------|
| Draft/published invitation files | Until project deletion | Retained (expired status) | Deleted with project |
| Guest records | Life of project | Retained | Anonymized (guest) or deleted (project) |
| Address book | Until customer deletes | Unaffected | Deleted on customer erasure |
| RSVP data | Life of project | Retained for organiser | Anonymized with guest |
| Wishlist/reservations | Life of project | Retained | Deleted with project |
| Photos | Life of project | Retained | Files deleted; metadata removed |
| Delivery logs | **24 months** (`RetentionPolicy::DELIVERY_LOG_MONTHS`) | Retained | Recipient hash + error message cleared |
| Event audit logs | **12 months** (`RetentionPolicy::EVENT_LOG_MONTHS`) | Retained | Pruned by scheduler |
| Rate-limit transients | **~1 hour** (per limiter) | N/A | Auto-expire |
| Order references | Per WooCommerce/commerce policy | Unaffected | **Retained** — documented in erasure output |
| Temp uploads (`tmp/`) | **1 hour** (`StorageLimits::TEMP_MAX_AGE_SECONDS`) | Cleanup job | Deleted |

---

## 6. Cleanup jobs (`src/Scheduling/RetentionScheduler.php`)

| Hook | Schedule | Purpose |
|------|----------|---------|
| `pks_oi_cleanup_temp` | Daily | Remove abandoned `tmp/` files per project |
| `pks_oi_prune_event_logs` | Weekly | Delete events older than 12 months |
| `pks_oi_prune_delivery_logs` | Monthly | Clear `recipient_hash` + `last_error_message` on deliveries older than 24 months |

All idempotent; reentrancy guarded on temp cleanup.

---

## 7. Data minimization (implemented)

- No IP addresses in `pks_oi_events`
- No raw tokens in logs or export payloads
- `recipient_hash` = SHA-256 of normalized e-mail for delivery dedup only; cleared after retention window
- Rate-limit keys use hashed identifiers with short transient TTL
- Export redaction strips token hashes, manifest paths, and SHA-256 photo checksums from export groups

---

## 8. Uninstall

`uninstall.php` **preserves all customer data by default**.

Optional destructive uninstall only when `PKS_OI_UNINSTALL_DELETE_DATA` is defined as `true` before uninstall — removes plugin options only (not custom tables or files). Document any future table-drop extension for legal review.

---

## 9. Tests

`tests/Integration/Privacy/PrivacyTest.php` covers:

- Owner export scope (no cross-owner leakage)
- Address book export
- Guest-only export scope
- Guest erasure + token invalidation
- Commerce record retention messaging
- Hard delete idempotency + job cancellation
- Archive + delivery cancellation
- Retention scheduler pruning
- Uninstall preservation default

---

## 10. Items requiring business/legal confirmation

| Question | Technical default | Risk if wrong |
|----------|-------------------|---------------|
| Delivery log retention period | 24 months | Compliance |
| Event log retention | 12 months | Support vs privacy |
| Post-expiry organiser access duration | Indefinite read-only in My Account | UX vs storage cost |
| Guest data retention after organiser delete | Delete with project | GDPR lawful basis |
| Destructive uninstall table/file removal | Not implemented — options only | Data loss |

Implementation uses the technical defaults above until legal confirms otherwise.
