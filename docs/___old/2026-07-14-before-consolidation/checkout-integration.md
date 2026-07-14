# Cart and checkout integration

**Status:** Implemented in Prompt 11 (`src/WooCommerce/Cart/`, `src/WooCommerce/Checkout/`)

---

## Flow

```text
Product page (PDF Builder customizer)
  → POST field/page/size/format
  → BPP_Woo_Cart_Functions::add_cart_item_data (priority 99)
  → InvitationCart annotates pks_oi_* markers (priority 100)
  → Cart session
  → Classic checkout
  → BPP_Hooks::save_order_meta (field/page/files)
  → OrderItemPayload persists _pks_oi_* references (priority 20)
  → ProjectOrderListener creates project on on-hold|processing|completed
```

---

## Cart markers (no large-data duplication)

| Cart key | Purpose |
|----------|---------|
| `pks_oi_invitation` | Line marker |
| `pks_oi_payload_version` | `1` |
| `pks_oi_payload_checksum` | SHA-256 manifest hash (keys, page lengths, size, format) |

PDF Builder `field`, `page`, `pa_bpp_size`, `pa_bpp_format`, `pdf-files` remain the payload source.

---

## Order item meta (OI references only)

| Meta key | Value |
|----------|-------|
| `_pks_oi_product_type` | `online_invitation` |
| `_pks_oi_payload_version` | `1` |
| `_pks_oi_payload_checksum` | manifest hash |

---

## Account requirement

When the cart contains an invitation:

- Guest checkout disabled (`pre_option_woocommerce_enable_guest_checkout` → `no`)
- Registration required / enabled
- Logged-in customers proceed normally
- WooCommerce password-setup e-mail only — never plaintext password

---

## Checkout Block limitation

**Production evidence:** theme uses classic `page-checkout.php` (Kasse v2), not `woocommerce/checkout` block.

`CheckoutBlockGuard` redirects block checkout to cart with an error when an invitation is in the cart. Store API checkout is blocked similarly.

---

## Validation

`CartPayloadValidator`:

1. Structural: non-empty `field`, `page`, `pa_bpp_size`, `pa_bpp_format`
2. Adapter: `validate_state()` when `bpp/integration/service` is available

Rejected at add-to-cart and re-checked at `woocommerce_checkout_create_order_line_item`.

---

## Manual QA

See `docs/manual-test-product-to-checkout.md`.

---

## Tests

```bash
composer test
```

Coverage: payload validation, cart annotation/session restore, account requirement, order meta references, mixed cart, invalid builder state rejection.
