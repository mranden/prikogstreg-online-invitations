# Data integrity review — Prompt 25 audit

**Status:** V1 static audit  
**Date:** 2026-07-14  
**Scope:** `prikogstreg-online-invitations` + `pdf-plugin`

---

## Verified (good)

| # | Control | Evidence |
|---|---------|----------|
| 1 | Project idempotency | `order_item_id` UNIQUE; `ProjectCreationLock`; duplicate hook safe |
| 2 | Schema constraints | UNIQUE on `storage_uuid`, `order_item_id`, `generic_token_hash`, `token_hash`, `idempotency_key` | `Schema.php` |
| 3 | Migration idempotency | `Migrator::install()` retried after lock expiry | `MigratorTest` |
| 4 | Repository SQL | `$wpdb->prepare()` + column allowlists | All `src/Database/Repositories/*` |
| 5 | State version conflicts | `expected_state_version` on save/publish | `ProjectStorage`, `ProjectStateService` |
| 6 | Published checksums | SHA-256 on published pages; load fails closed | `PublicInvitationLoader`, `PublicInvitationTest` |
| 7 | Path safety | `StoragePath` blocks `..`; allowlisted prefixes | `StoragePathTest`, `ProjectStorageTest` |
| 8 | Token storage | SHA-256 hashes only in DB | `InvitationToken`, `InvitationTokenTest` |
| 9 | Welcome email once | `welcome:{project_id}` idempotency + option flag | `WelcomeScheduler`, `ProjectServiceTest` |
| 10 | Delivery idempotency | `idempotency_key` UNIQUE | `DeliveryTest` |
| 11 | Wishlist races | Atomic reservation service | `WishlistTest` |
| 12 | Refund restriction | Full line refund → `RESTRICTED` + unpublish | `LifecycleTest`, `InvitationFlowChainTest` |
| 13 | Expiration | Event + 90 days; override support | `LifecycleTest`, `ExpirationScheduler` |
| 14 | Guest snapshot independence | Address-book delete does not mutate guest rows | `GuestManagementTest` |
| 15 | CSV integrity | Formula prefix neutralized on export/import | `GuestCsvTest` |
| 16 | Privacy erasure idempotent | `PrivacyTest` | Eraser + hard-delete |
| 17 | HPOS order reads | `wc_get_order()` in order listeners | `HposOrderTest` |
| 18 | Order item payload (pdf) | File pointer meta; legacy meta stripped on save | `BPP_Order_Item_Storage` |

---

## Requires manual test

| Area | Notes |
|------|-------|
| Concurrent project creation | Two simultaneous WC webhooks for same order item |
| Partial refunds | Only full invitation line refund restricts today |
| HPOS + pdf-plugin cron | `class-bpp-cron.php` queries both `wp_wc_orders` and `wp_posts` |
| Publish during edit conflict | Two browser tabs saving design + publish |
| Photo orphan cleanup | `PhotoCleanupService` TTL on pending files |

---

## Release blocker

None in Online Invitations after Prompt 25 pass.

---

## Non-blocking recommendations

| Item | Notes |
|------|-------|
| CPT `project_id` vs table `project_id` | Intentional dual storage; CPT is admin shell |
| `generic_token_hash` NULL on revoke | By design; resolver returns null |
| pdf-plugin checkout meta overlap | Multiple handlers write line-item meta; verify no clobbering in staging mixed cart |

---

## Static search results (OI `src/`)

| Pattern | Result |
|---------|--------|
| `unserialize(` | None |
| Direct SQL concatenation with user input | None (table names from prefix only) |
| `get_post(` on orders | None |
