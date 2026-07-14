# Architecture decisions — Prikogstreg Online Invitations

**Status:** Accepted for V1  
**Date:** 2026-07-14  
**Supersedes:** N/A (initial plan)

---

## ADR-001 — CPT is admin shell only; custom tables own domain data

**Decision:** Register private CPT `pks_oi_project` for WordPress admin UI and capabilities. All domain state lives in `pks_oi_*` custom tables.

**Rationale:** CPT post content/meta is unsuitable for high-cardinality relational data and complicates HPOS-safe queries.

**Consequences:** Repositories are mandatory. Post status must not conflict with `pks_oi_projects.status`.

---

## ADR-002 — File-backed builder state; tables store pointers only

**Decision:** Editable builder state, page HTML, and published snapshots are stored in private project files. Tables store `storage_uuid`, manifest paths, versions, and checksums.

**Rationale:** Builder payloads can exceed MySQL row limits; separation enables atomic writes and published/draft isolation.

**Consequences:** `ProjectStorage`, `AtomicFileWriter`, and manifest versioning are required before My Account editing.

---

## ADR-003 — Order item is purchase source; project storage is runtime source

**Decision:** On project creation, import/copy order-item payload once into project-owned storage. Normal editing reads/writes project files, not `_bpp_custom_data_file`.

**Rationale:** Order-item files may be deleted by PDF Builder cron after ~3 months (`BPP_Cron::delete_old_order_meta`).

**Consequences:** Project creation must run before cron deletion window matters; order references retained for audit only.

---

## ADR-004 — PDF Builder integration via adapter filter only

**Decision:** Online Invitations discovers `\BPP\Integration\Builder_Adapter_Interface` through `apply_filters( 'bpp/integration/service', null )`. No scattered `BPP_*` calls in domain code.

**Rationale:** Audit confirms no formal public API and unsafe direct coupling.

**Consequences:** PDF Builder Prompts 3–6 must land before My Account editor (Prompt 14).

**`load_state()` / `save_state()` semantics:**

| Method | Owner of persistence | Behavior |
|--------|---------------------|----------|
| `load_state( $context )` | PDF Builder reads; OI interprets | Load from `order_item_id` legacy file, or normalize caller-supplied state array from project files. Returns canonical state array. |
| `save_state( $state, $context )` | Online Invitations | Adapter validates/normalizes/migrates only. Returns canonical state for `ProjectStorage` to write. Adapter does **not** write project files. |

No circular calls: OI → adapter → BPP internals; adapter never calls OI services.

---

## ADR-005 — Token hashes only; no raw tokens in storage

**Decision:** Personal and generic tokens are 32-byte random, URL-safe base64 encoded for URLs. Store `hash('sha256', $raw_token)` with `token_version`.

**Rationale:** Database breach must not expose live invitation links.

**Consequences:** Raw token shown only once at generation/rotation in owner UI.

---

## ADR-006 — Published snapshot is mandatory for public routes

**Decision:** Public invitation HTML comes only from sanitized `pages/published/` files created at publish time. Raw editable `pages/editable/` never serves logged-out visitors.

**Rationale:** Customer-supplied `page[]` HTML is untrusted (audit §12).

**Consequences:** Publish fails closed; no fallback to draft HTML.

---

## ADR-007 — Unlimited guests in V1

**Decision:** No guest capacity counters, limits, or upgrade UI. Schema has no capacity enforcement columns.

**Rationale:** Confirmed product decision; V2 handles paid capacity.

---

## ADR-008 — Action Scheduler via WooCommerce; no custom queue

**Decision:** Use WooCommerce-bundled Action Scheduler (confirmed present: v3.9.3 in `woocommerce/packages/action-scheduler/`).

**Group:** `pks-oi`

**Consequences:** Jobs must be idempotent with `idempotency_key` in `pks_oi_deliveries`.

---

## ADR-009 — Classic checkout is primary; Blocks deferred

**Decision:** Implement account requirement and cart payload preservation for classic checkout first.

**Evidence:** Active theme `prikogstreg` uses `page-checkout.php` (Template: Kasse v2), `woocommerce/checkout/form-checkout.php`, and `bowe-checkout` layout — not `woocommerce/checkout` block.

**Consequences:** Prompt 11 documents Checkout Block limitation; bridge only if production verification shows Blocks checkout page.

---

## ADR-010 — Private storage root with protected fallback

**Decision:**

1. **Preferred:** Constant `PKS_OI_STORAGE_PATH` pointing outside web root (e.g. `/var/private/prikogstreg-oi/`).
2. **Fallback:** `wp-content/uploads/pks-oi-private/` with `.htaccess` / `index.php` deny rules and all delivery via PHP authorization controller.

**Rationale:** Builder and photo files must not be directly web-accessible.

---

## ADR-011 — No service container

**Decision:** Root `Plugin` orchestrator with constructor injection; no DI container.

**Rationale:** WordPress plugin conventions; no evidence container is needed.

---

## ADR-012 — Theme owns presentation only

**Decision:** Business logic stays in plugins. Theme may override templates at `theme/prikogstreg-online-invitations/`.

**Evidence:** Theme already calls `BPP_PDF_Plugin::content_single_product()` and `bpp_wc_attribute_html` — presentation integration only.

---

## ADR-013 — Wishlist reservation identity hidden by default

**Decision:** `show_reserver_identity` defaults to `0`. Organiser sees counts; not guest names unless explicitly enabled.

---

## ADR-014 — Guest photos: moderation only; no automatic public gallery

**Decision:** V1 provides upload, organiser approve/reject/download/delete. Approved photos are **not** auto-published to a public gallery.

---

## ADR-015 — Minimum platform versions

| Component | Minimum | Evidence |
|-----------|---------|----------|
| PHP | **8.1** | Local dev 8.4.22; mPDF 8.0.14; WooCommerce 10.8.1 requires 7.4; agent default 8.1 when no stricter site floor |
| WordPress | **6.5** | Action Scheduler 3.9.3 requires 6.5; workspace core 7.0 |
| WooCommerce | **8.0** | HPOS CRUD, Action Scheduler integration; installed 10.8.1 |

PDF Builder declares no minimums; treat as same floor as Online Invitations.

---

## ADR-016 — Build pipelines remain separate

| Plugin | Pipeline | Output |
|--------|----------|--------|
| `pdf-plugin` | Existing Webpack 5 (`npm run build`) | `dist/js/`, `dist/css/` |
| `prikogstreg-online-invitations` | esbuild + Sass (`npm run build`) | `assets/build/` |

Do not replace PDF Builder Webpack.

---

## Rejected alternatives

| Alternative | Why rejected |
|-------------|--------------|
| Store builder HTML in `LONGTEXT` columns | Size, backup, and publish-snapshot separation |
| Public CPT permalinks for projects | Security; bearer tokens required |
| Direct `BPP_Order_Item_Storage` in controllers | Coupling; no ownership checks |
| SMS/phone verification in V1 | Explicit V2 exclusion |
| Guest capacity in product stock | Confirmed anti-pattern in rules |
