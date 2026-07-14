# Prompt 6 — State validation, schema migration, preview, and safe public HTML

**Status:** Authoritative handoff for `pdf-plugin` maintainers  
**Date:** 2026-07-14  
**Prerequisites:** Prompt 4 (adapter interface), Prompt 5 (editor context)  
**Constraint:** Implementation code lives in `pdf-plugin` only. This document is the Online Invitations deliverable while `pdf-plugin` remains unchanged.

---

## 1. Goal

Complete the PDF Builder adapter rendering contract:

| Method | Purpose |
|--------|---------|
| `create_initial_state()` | Empty canonical state from template |
| `validate_state()` | Server-side field/template/size validation |
| `get_schema_version()` | Resolve `0` (legacy) or `1` (canonical) |
| `migrate_state()` | Legacy → current schema |
| `render_preview_html()` | Draft HTML for authenticated owner preview |
| `render_public_html()` | Sanitized HTML for published invitations |
| `save_state()` | Return normalized canonical state (OI persists) |

**Out of scope:** Logged-out public routes, Online Invitations `PublishedHtmlSanitizer` (OI second layer, Prompt 15), My Account controllers.

---

## 2. Canonical state shape

### 2.1 PHPDoc array shape

```php
/**
 * Canonical PDF Builder customer state (schema_version "1").
 *
 * @phpstan-type BppFieldText array{text?:string, font?:string}
 * @phpstan-type BppFieldImage array{data?:string, visible?:bool}
 * @phpstan-type BppFieldLayer array{visible?:bool}
 * @phpstan-type BppFieldValue BppFieldText|BppFieldImage|BppFieldLayer
 *
 * @phpstan-type BppCanonicalState array{
 *   schema_version: string,
 *   template_id: int,
 *   product_id: int,
 *   size: string,
 *   format: string,
 *   field: array<string, BppFieldValue>,
 *   page: list<string>,
 *   _pages_thumbnails: list<array{thumbnail?:string, page_name?:string}>
 * }
 */
```

### 2.2 JSON fixtures (this repo)

| File | Purpose |
|------|---------|
| `docs/fixtures/builder-canonical-state-v1.json` | Current schema example |
| `docs/fixtures/builder-legacy-state-v0.json` | Order-item import (no `schema_version`) |
| `docs/fixtures/public-html-sanitizer-fixtures.json` | XSS regression inputs |

### 2.3 Checksum for deterministic tests

```php
$checksum = hash( 'sha256', wp_json_encode( State_Normalizer::export_canonical( $state ) ) );
```

PHPUnit asserts stable checksum for `builder-canonical-state-v1.json` after normalize + migrate.

---

## 3. Schema versions

| Version | Constant | When |
|---------|----------|------|
| `0` | `Builder_Schema::LEGACY_VERSION` | Order-item file / cart payload without `schema_version` |
| `1` | `Builder_Schema::CURRENT_VERSION` | Canonical export after validation |

### `get_schema_version( array $state ): string`

```php
return (string) ( $state['schema_version'] ?? Builder_Schema::LEGACY_VERSION );
```

### `migrate_state( $state, $from, $to ): array|WP_Error`

| Path | Behavior |
|------|----------|
| `0` → `1` | `State_Normalizer::export_canonical()` — add `schema_version`, `template_id`, `product_id`, `size`, `format` from context when missing |
| `1` → `1` | No-op normalize |
| Other | `WP_Error( 'bpp_schema_unsupported', ... )` |

**Legacy order-item loading:** `load_state()` with `order_item_id` continues to use `BPP_Order_Item_Storage::get_payload()`; migration runs on read when consumer requests canonical export.

---

## 4. PHP classes (`pdf-plugin/src/Integration/`)

| Class | Responsibility |
|-------|----------------|
| `State_Validator.php` | Template-aware field UUID/type/required checks |
| `State_Normalizer.php` | Shape + schema_version (from Prompt 4) |
| `Public_Html_Renderer.php` | Builder-specific allowlist sanitizer |
| `Preview_Html_Renderer.php` | Draft merge without full editor enqueue |
| `Template_Field_Registry.php` | Map template → allowed UUIDs and field types |

### 4.1 `create_initial_state( $template_id, $context )`

1. `new BPP_Product( (int) $template_id )` — error if inactive.
2. Build empty `field` map; `page` from template `low_res_html` per page index.
3. Copy `size`/`format` defaults from product (`default_size`, foldable → `folded`/`flat`).
4. `State_Normalizer::export_canonical()`.
5. `apply_filters( 'bpp/initial_customer_state', $state, $context )`.

### 4.2 `validate_state( $state, $context )`

Validation order:

1. Normalize via `State_Normalizer::normalize()`.
2. **Counts:** `count( page ) <= 20` (match `BPP_Ajax_Security::MAX_CANVAS_PAGES`).
3. **Byte size:** total `strlen` of `page[]` strings + base64 image payloads ≤ 15 MB per field (match AJAX limits).
4. **Template registry:** load `BPP_Product` from `context['product_id']` or `context['template_id']`.
5. **Unknown UUIDs:** reject `field` keys not in template `pages[].fields`.
6. **Unknown types:** each UUID must match template field `type` (`text`, `image`, `layer`).
7. **Per-type rules** (mirror client `acceptCustomOrder` server-side):

| Type | Rules |
|------|-------|
| `text` | `text` string ≤ 10 000 chars; `font` slug must exist in `BPP_PDF_Plugin::get_fonts()` or template allowlist |
| `image` | `data` must be empty, `https?://` on allowlisted host, or `data:image/(jpeg\|png\|webp);base64,` under 15 MB decoded |
| `layer` | `visible` boolean only |

8. **Required fields:** template `conditions.required` → non-empty `text` or present image `data`.
9. **Size/format:** `size` slug in product `available_sizes` + `pa_bpp_size` terms; `format` in `flat`, `folded`.
10. `apply_filters( 'bpp/validated_customer_state', $state, $context )`.
11. Return normalized state or `WP_Error` with codes: `bpp_invalid_state`, `bpp_unknown_field`, `bpp_invalid_size_format`, `bpp_request_too_large`.

### 4.3 `save_state( $state, $context )`

```php
$validated = $this->validate_state( $state, $context );
if ( is_wp_error( $validated ) ) {
    return $validated;
}
$canonical = State_Normalizer::export_canonical( $validated );
do_action( 'bpp/customer_state_saved', $canonical, $context );
// Legacy order-item write only when order_id + order_item_id in context
return $canonical;
```

**OI owns project file persistence** — adapter never writes `pks_oi_*` tables or project directories.

---

## 5. Public HTML rendering

### 5.1 Pipeline

```text
render_public_html( $state, $context )
  → validate_state( $state, $context )  // is_public => true in context
  → compose HTML from page[] (or server merge)
  → Public_Html_Renderer::sanitize( $html, $context )
  → prepend scoped BPP_fonts_css() output (font URLs from trusted uploads only)
  → apply_filters( 'bpp/public_html', $html, $state, $context )
  → return string (never WP_Error on success with empty — return empty string if no pages)
```

```text
render_preview_html( $state, $context )
  → validate_state (is_preview allowed)
  → Preview_Html_Renderer::render (less strict — still no script/iframe)
  → apply_filters( 'bpp/preview_html', $html, $state, $context )
```

### 5.2 Composition (no editor JS required)

Use stored `page[]` HTML strings — **do not** enqueue `public.dist.js` for public/preview display unless manual QA proves layout requires it (default: **no editor bundle**).

Wrap output:

```html
<div class="bpp-public-invitation" data-bpp-schema-version="1">
  <style>/* scoped scale + fonts */</style>
  <!-- sanitized page HTML -->
</div>
```

### 5.3 Builder-specific allowlist (`Public_Html_Renderer`)

**Do not rely on `wp_kses_post()` alone.** Implement explicit allowlist documented below.

#### Allowed tags

`div`, `span`, `p`, `br`, `img`, `style` (single scoped block only), `strong`, `em`, `b`, `i`

#### Forbidden (strip entirely)

`script`, `iframe`, `object`, `embed`, `form`, `input`, `button`, `link`, `meta`, `base`, `svg` (V1 — simplify attack surface; revisit if templates require SVG)

#### Allowed attributes

| Tag | Attributes |
|-----|------------|
| `div`, `span`, `p` | `class`, `id`, `data-page`, `data-uuid`, `style` (filtered) |
| `img` | `class`, `src`, `alt`, `width`, `height`, `style` (filtered) |
| `style` | `type` only; content filtered |

#### Allowed `class` prefixes (others stripped)

`customizer-page-content`, `bpp-`, `page-`, `textFitted`, `foldable`, `front`, `back`, `active`

#### Style property allowlist (inline + `<style>` blocks)

`font-family`, `font-size`, `font-weight`, `color`, `background-color`, `background-image`, `width`, `height`, `max-width`, `max-height`, `margin`, `padding`, `text-align`, `line-height`, `letter-spacing`, `opacity`, `transform`, `display`, `position`, `top`, `left`, `border-radius`, `object-fit`

**Strip:** `@import`, `expression(`, `javascript:`, `behavior:`, `-moz-binding`, `url(` pointing off-site except `fonts`/`uploads` on same host

#### URL rules

- `src` / `background-image`: `https?://` same host, or `data:image/(jpeg|png|webp);base64,`
- Reject `javascript:`, `vbscript:`, `data:text/html`

### 5.4 Dual-layer sanitization (OI)

| Layer | Owner | When |
|-------|-------|------|
| Builder allowlist | `Public_Html_Renderer` (pdf-plugin) | `render_public_html()` |
| Publication allowlist | `PublishedHtmlSanitizer` (OI plugin, Prompt 15) | Before write to `pages/published/` |

Published files must pass **both** layers.

---

## 6. Responsive scaling limitations (documented)

| Limitation | Detail |
|------------|--------|
| Fixed canvas | Template HTML uses px dimensions from admin customizer |
| Mobile | Public view uses CSS `transform: scale()` on `.bpp-public-invitation` wrapper — not reflow |
| Fonts | `@font-face` from `BPP_fonts_css()` — ensure WOFF/WOFF2 URLs are same-origin |
| Multi-page | Each `page[]` block stacked vertically; no turn.js on public view |
| Images | Guest-uploaded base64 in draft may be replaced with file URLs on publish (OI Prompt 9) |

Manual QA required: iOS Safari, Android Chrome, 320px width (see test-plan §3.2).

---

## 7. Tests (`pdf-plugin/tests/`)

### 7.1 Unit — `State_ValidatorTest`

| Test | Input |
|------|-------|
| Accepts canonical v1 fixture | `builder-canonical-state-v1.json` |
| Rejects unknown field UUID | Extra UUID not in template |
| Rejects invalid size/format | `size=invalid` |
| Rejects oversized page array | 21 pages |
| Rejects script in text field | `text: '<script>'` |
| Required text field empty | Template marks required |

### 7.2 Unit — `SchemaMigrationTest`

| Test | Assert |
|------|--------|
| Legacy v0 → v1 | `schema_version === '1'` |
| Checksum stable | SHA-256 of canonical fixture |
| Unsupported migration | `1` → `99` returns `WP_Error` |

### 7.3 Unit — `Public_Html_RendererTest`

Load `docs/fixtures/public-html-sanitizer-fixtures.json` (copy to `pdf-plugin/tests/Fixtures/` on implement):

| Fixture ID | Assertion |
|------------|-----------|
| `script-tag` | no `<script` |
| `onerror-attribute` | no `onerror` |
| `javascript-url` | no `javascript:` |
| `malicious-style-expression` | no `expression(` |
| `external-iframe` | no `<iframe` |
| `legitimate-builder-markup` | preserves `bpp-drag-element-`, text content |

### 7.4 Integration — `AdapterRenderingTest`

| Test | Assert |
|------|--------|
| `render_public_html` returns string | No editor script tags |
| `render_preview_html` with draft state | Contains page content |
| `save_state` returns canonical | Has `schema_version` |
| Legacy `load_state` + migrate | Roundtrip v1 |

### 7.5 Commands (when implemented)

```bash
cd pdf-plugin && composer test && npm run build
```

**No tests run in this docs-only delivery.**

---

## 8. Hooks

| Hook | Type | When |
|------|------|------|
| `bpp/initial_customer_state` | filter | After `create_initial_state` |
| `bpp/validated_customer_state` | filter | After `validate_state` |
| `bpp/customer_state_saved` | action | After `save_state` normalization |
| `bpp/preview_html` | filter | After `render_preview_html` |
| `bpp/public_html` | filter | After `render_public_html` sanitization |

---

## 9. Online Invitations consumer (Prompt 7+)

```php
$adapter = apply_filters( 'bpp/integration/service', null );
$state   = $adapter->load_state( [ 'state' => $draft_from_project_files ] + $context );

$validated = $adapter->validate_state( $state, $context );
if ( is_wp_error( $validated ) ) { /* block publish */ }

$html = $adapter->render_public_html( $validated, [ 'is_public' => true ] + $context );
// OI PublishedHtmlSanitizer::sanitize( $html ) — second layer
// write pages/published/page-001.html
```

---

## 10. Definition of done (pdf-plugin)

- [ ] Canonical state PHPDoc + JSON fixtures copied to `tests/Fixtures/`
- [ ] `validate_state` mirrors text/image/layer rules server-side
- [ ] Unknown UUIDs and invalid size/format rejected
- [ ] `migrate_state` supports `0` → `1`
- [ ] `Public_Html_Renderer` allowlist documented and tested (not `wp_kses_post` alone)
- [ ] `render_public_html` / `render_preview_html` do not enqueue editor bundle by default
- [ ] Malicious fixture tests pass
- [ ] `composer test` + `npm run build` (if assets change) — report actual output
- [ ] Responsive limitations documented in `pdf-plugin/docs/manual-regression-checklist.md`

---

## 11. Dependency order

```text
Prompt 4 (adapter) → Prompt 5 (editor context) → Prompt 6 (this spec) → Prompt 7 (OI plugin)
```

Prompt 6 completes the builder rendering contract; it does **not** expose public invitation URLs.
