# Product, cart, checkout, and project creation

**Last verified:** 2026-07-14 (product configurator frontend shipped)

---

## Product type: `online_invitation`

**Class:** `WC_Product_Online_Invitation` extends `WC_Product_Simple`  
**Registrar:** `ProductTypeRegistrar`

| Behaviour | Implementation |
|-----------|----------------|
| Virtual product | `is_virtual()` true |
| Sold individually | `is_sold_individually()` true |
| Quantity 1 min/max | Product class + `QuantityGuard` |
| Add-to-cart template | `OnlineInvitationProductFrontend` → `templates/product/add-to-cart-online-invitation.php` |
| Admin data panel | `ProductDataPanel` — envelope, BPP link (`prdid` query param) |

---

## Pre-purchase customer flow

1. Customer opens single product page
2. `ProductReadiness` shows customer-friendly unavailable state when configuration incomplete; shop managers with `manage_woocommerce` see diagnostic codes
3. Theme renders BPP canvas in left column (`#customizer-area`)
4. `OnlineInvitationProductFrontend` renders `.pks-oi-product-configurator` form:
   - `EnvelopeFrontend` preview (from existing product meta / `EnvelopeDesign`)
   - Canvas hint when BPP customizable
   - `BuilderFrontendBridge` → BPP field form + hidden size/format
   - BPP preview + purchase hooks (preserved WC hook order)
5. Customer edits fields; BPP JS updates `page[]` / `field[]`
6. Add to cart → `CartPayloadValidator` validates structure
7. `InvitationCart` annotates line with OI markers + checksum

**Product-page assets:** OI loads `assets/build/css/product.css` and `product.js` only on `online_invitation` pages. BPP loads its own editor assets. The public invitation app (`public.css` / `public.js`) is **not** enqueued on product pages.

**Cart rejection cases:**

- Missing/invalid `field`, `page`, size, format
- BPP defaults unresolved (`BppAttributeDefaults`)
- Quantity ≠ 1
- Inactive or missing BPP template (unless `builder_optional`)

---

## Cart markers

`InvitationCart` adds cart line metadata:

- Invitation product flag
- Payload checksum (manifest-based, not full raw POST)
- Reference for checkout persistence

**Mixed cart:** Only invitation lines are annotated; other products unaffected (`CartCheckoutTest`).

---

## Checkout rules

| Rule | Class |
|------|-------|
| Account required when cart has invitation | `AccountRequirement` |
| Guest must opt into account creation | Validated at checkout |
| Checkout Blocks blocked | `CheckoutBlockGuard` |
| Classic checkout only | Documented V1 limitation |
| Order line payload validated | `OrderItemPayload` |

---

## Order → project creation

**Listener:** `ProjectOrderRegistrar` on qualifying order status transitions.

**Factory:** `ProjectFactory::build_initial_row()` copies product meta:

- Envelope/background presets, locale, reminder offset, feature defaults
- Links `order_id`, `order_item_id`, `product_id`, `user_id`

**Import:** `ProjectService::import_for_project()`

1. `ProjectImportGuard` prevents duplicate/incompatible re-import
2. Adapter `load_state` mode `import` from `BPP_Order_Item_Storage`
3. `ProjectStorage::import_complete_snapshot()` — state + pages + envelope
4. Failed import recorded; `ProjectImportRetry` on repeated hooks

**Idempotency:** Same `order_item_id` does not create duplicate projects.

---

## Post-import project state

| Store | Content |
|-------|---------|
| DB `pks_oi_projects` | Event fields, tokens, versions, status |
| `state/current.json` | Builder state |
| `pages/editable/` | Editable HTML |
| `envelope/manifest.json` | Envelope snapshot |

Welcome e-mail scheduled via `WelcomeScheduler`.

---

## Refunds and restrictions

`ProjectRefundListener` + `ProjectRestrictionService` set `restricted_at_utc` on qualifying refunds — public access denied uniformly.

---

## Admin product checklist

Before selling:

1. Product type **Online invitation**
2. Price set
3. `_bpp_product` **active** with text/image fields configured
4. Permitted BPP size enabled (default size must be available)
5. Envelope preset (+ optional image) configured
6. Flush permalinks if public URLs 404

---

## HPOS

Required. `Compatibility::declare_hpos_compatibility()` and runtime check in `Requirements`. Order/item IDs used via WooCommerce CRUD APIs.
