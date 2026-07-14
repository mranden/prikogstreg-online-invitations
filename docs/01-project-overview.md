# Project overview

**Plugin:** Prikogstreg Online Invitations (`prikogstreg-online-invitations`)  
**Version:** 0.1.0  
**Namespace:** `PrikOgStreg\OnlineInvitations\`  
**Last verified against code:** 2026-07-14

---

## What this plugin does

Customers buy an **online invitation** WooCommerce product, customise the inner poster in the **PDF Builder** on the product page, and complete checkout. After a qualifying order, the plugin creates one **invitation project** per line item, imports builder state from the order payload, and exposes management in **WooCommerce My Account**. When the organiser publishes, guests open a token-based public URL showing an **animated envelope** and the **published inner HTML**, with RSVP, wishlist, and photo features outside the poster document.

---

## Workspace components

| Component | Repository / path | Role |
|-----------|-------------------|------|
| **PDF Builder** | `pdf-plugin` | Admin poster design, product-page editor, `page[]` / `field[]`, cart/order payload |
| **Online Invitations** | `prikogstreg-online-invitations` (this plugin) | Product type, envelope, projects, publication, guests, public routes |
| **Prikogstreg theme** | Active theme | Layout, WooCommerce templates, BPP canvas placement, styling only |
| **WooCommerce** | Core dependency | Commerce, HPOS orders, My Account, checkout |

---

## V1 scope (verified in code unless noted)

| Decision | Status |
|----------|--------|
| PDF Builder is the inner designer | **Implemented** — pre-purchase only |
| Envelope separate from inner HTML | **Implemented** — product meta + project snapshot |
| Envelope configured on `online_invitation` product | **Implemented** |
| Envelope snapshotted into project | **Implemented** — `envelope/manifest.json` |
| Customer finalises inner design before checkout | **Implemented** — cart validation |
| No full PDF editor reopen after purchase | **Implemented** — My Account design section uses project state, not storefront editor |
| One purchase → one project | **Implemented** — `order_item_id` unique |
| Quantity always one | **Implemented** — product class + guards |
| Unlimited guests (no capacity packages) | **Implemented** — no guest cap enforcement |
| Classic checkout supported | **Implemented** — `CheckoutBlockGuard` |
| Checkout Blocks not supported for invitation carts | **Implemented** — guard rejects blocks checkout |
| Order-item payload is purchase source | **Implemented** — `BPP_Order_Item_Storage` via adapter |
| Project storage is long-term source | **Implemented** — `ProjectStorage` |
| Published HTML is public source | **Implemented** — `pages/published/` only |
| Public must not depend on order payload | **Implemented** |
| Public must not require full PDF editor JS | **Implemented** — poster CSS snapshotted at publish |

---

## Release status

Automated tests: **328/328 passing** (unit 111, integration 216, e2e 1).  
Browser E2E on a fully configured staging product: **not complete** (see `10-testing-release-operations-and-roadmap.md`).  
**Operational verdict:** conditional go — post-purchase and public paths are code-complete; storefront customize → checkout needs configured product + manual QA.

---

## Documentation map

| File | Contents |
|------|----------|
| `01-project-overview.md` | This file |
| `02-codebase-map-and-bootstrap.md` | Directory layout, boot order, build |
| `03-architecture-and-responsibilities.md` | PDF / OI / theme boundaries |
| `04-domain-and-data-model.md` | Tables, entities, tokens |
| `05-pdf-builder-and-envelope-integration.md` | BPP adapter, envelope, storefront bridge |
| `06-product-cart-checkout-and-project-creation.md` | Product type → order → project |
| `07-storage-publishing-and-public-rendering.md` | Files, publish, public invitation |
| `08-my-account-guests-rsvp-wishlist-and-photos.md` | Organiser and guest features |
| `09-security-privacy-and-permissions.md` | Auth, sanitization, retention |
| `10-testing-release-operations-and-roadmap.md` | Tests, deploy, QA, roadmap |

Historical documentation is preserved under `docs/___old/2026-07-14-before-consolidation/`.

---

## Quick start

```bash
composer install
composer test
npm install && npm run build
```

Production: define `PKS_OI_STORAGE_PATH` outside the web root, enable HPOS, activate PDF Builder with `bpp/integration/service`, flush permalinks after deploy.
