# Performance review — Prompt 25 audit

**Status:** V1 static audit  
**Date:** 2026-07-14  
**Scope:** `prikogstreg-online-invitations` + `pdf-plugin`

---

## Verified (good)

| Area | Finding | Evidence |
|------|---------|----------|
| Project list queries | Summary columns only; no published HTML loaded | `ProjectRepository::list_summary_for_user()` |
| Pagination | My Account list uses `PER_PAGE = 10` | `ProjectController::PER_PAGE` |
| Guest list | Paginated with `LIMIT`/`OFFSET` | `GuestRepository::list_for_project()` |
| Delivery dedup | `idempotency_key` UNIQUE prevents duplicate sends | `DeliveryRepository`, `DeliveryQueueService` |
| File I/O | Atomic writes with checksums | `AtomicFileWriter`, `ProjectStorage` |
| Production assets (OI) | `assets/build/*` enqueued; no `assets/src` in PHP | `PublicController`, `MyAccountRegistrar`, `AdminAssets` |
| HPOS declaration | `custom_order_tables` compatibility declared | `WooCommerce/Compatibility.php` |
| Order access | `wc_get_order()` only; no direct `get_post` on orders | Grep across `src/` |

---

## Requires manual test

| Area | Risk | Notes |
|------|------|-------|
| PDF generation CPU/memory | Product-page `save_cart_pdf` and admin `create_pdf_html` raise limits | pdf-plugin sets high memory/time; load-test on staging |
| Public invitation canvas | Large published HTML + builder fonts | Mobile 320px manual matrix (test-plan M4, M7) |
| Photo processing | GD resize on upload | Test 10 MB / 25 MP boundary on real hosting |
| Action Scheduler volume | Reminder + welcome + delivery jobs | Staging with many guests |
| Webpack bundle size | `public.dist.js` ~1.64 MiB | Webpack warns; acceptable for product page but monitor 3G (M18) |

---

## Release blocker

None identified in Online Invitations from static analysis.

---

## Non-blocking recommendations

| Item | Recommendation |
|------|----------------|
| `ActionSchedulerBridge::is_scheduled()` | Does not pass `unique_key` to AS lookup; delivery DB idempotency compensates |
| Guest send transients | Raw tokens in transients for email delivery (90-day TTL); consider hashing in transient key only |
| pdf-plugin duplicate checkout hooks | Three `woocommerce_checkout_create_order_line_item` handlers; document ownership |
| pdf-plugin `cart-pdf.js` | Loaded from `assets/js/` not `dist/`; small file, low risk |
| Repository `SELECT *` | Acceptable for single-row lookups; list endpoints use column subsets where it matters |

---

## Commands run (2026-07-14)

```bash
cd prikogstreg-online-invitations && composer test && npm run build
cd pdf-plugin && composer test && npm run build
```
