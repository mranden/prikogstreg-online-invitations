# Prompt 5 — Context-aware PDF Builder editor (implementation spec)

**Status:** Authoritative handoff for `pdf-plugin` maintainers  
**Date:** 2026-07-14  
**Consumer:** Online Invitations plugin (Prompt 7+) — **no My Account controller in this prompt**  
**Constraint:** Implementation code lives in `pdf-plugin` only. This document is the deliverable for the Online Invitations repo while `pdf-plugin` remains unchanged.

---

## 1. Goal

The PDF Builder adapter must render the **existing storefront editor** inside a controlled Online Invitation My Account context **without** invoking WooCommerce add-to-cart behavior.

Prerequisite: Prompt 4 (`BPP\Integration\*` + `bpp/integration/service`) must land in `pdf-plugin` before or together with this work.

---

## 2. Current baseline (verified in `pdf-plugin` master)

| Concern | Location | Limitation |
|---------|----------|------------|
| Editor assets | `BPP_PDF_Plugin::wp_enqueue_scripts()` | Gated by `is_product()` |
| Editor markup | `BPP_PDF_Plugin::content_single_product()` | Uses `global $post` when `$id == -1` |
| Field form | `BPP_Product::render_product_customizer_form()` | Emits `#product-addons-for-customizer_*` |
| Save path | `customizer-product.js` → `addToCart()` | Clicks `.single_add_to_cart_button`, calls `save_cart_pdf` |
| Accept flow | `customizer-public.js` → `acceptCustomOrder()` | Ends in `addToCart()` |

---

## 3. Architecture

```text
Online Invitations (later Prompt 7)
  → validates ownership, assembles $context server-side
  → $adapter = apply_filters( 'bpp/integration/service', null )
  → $adapter->enqueue_editor_assets( $context )
  → echo $adapter->render_editor( $state, $context )

pdf-plugin
  → Integration adapter delegates to extracted services:
      BPP_Editor_Asset_Loader::enqueue( $context )
      BPP_Editor_Renderer::render( $state, $context )
  → product-page path unchanged:
      wp_enqueue_scripts → enqueue when is_product()
      theme → content_single_product()
```

---

## 4. Editor modes

| Mode | PHP `context['mode']` | Add-to-cart | Save mechanism |
|------|----------------------|-------------|----------------|
| `product` | `product` (default on single product) | **Yes** — existing flow | `save_cart_pdf` + cart POST |
| `project_edit` | `project_edit` | **No** | `bpp:save-requested` CustomEvent → OI listener persists |
| `project_preview` | `project_preview` | **No** | Read-only; optional `bpp:preview-generated` |

---

## 5. PHP implementation checklist (`pdf-plugin`)

### 5.1 New support classes

| File | Responsibility |
|------|----------------|
| `src/Integration/Editor_Asset_Loader.php` | Extract enqueue/localize from `wp_enqueue_scripts` |
| `src/Integration/Editor_Renderer.php` | Extract markup from `content_single_product` + field form |
| `src/Integration/Editor_Instance.php` | Generate `instance_id`, validate safe JS context export |

### 5.2 `Editor_Asset_Loader::enqueue( array $context ): void`

**Inputs (server-owned context):**

```php
[
    'source'      => 'online_invitation',
    'mode'        => 'product|project_edit|project_preview',
    'product_id'  => 456,          // required
    'template_id' => 456,          // fallback to product_id
    'locale'      => 'da_DK',
    'size'        => 'a5',
    'format'      => 'flat',
    'page_count'  => 2,            // optional hint
]
```

**Must NOT pass to JavaScript:** `user_id`, `order_id`, `order_item_id`, `project_id`, storage paths, tokens.

**Localized object:** `BPP_EDITOR_CONTEXT` (new) — keep legacy `BPP_PUBLIC_OBJ` on product pages for BC.

```php
[
    'instanceId'    => 'bpp-editor-' . wp_generate_uuid4(),
    'mode'          => 'project_edit',
    'productId'     => 456,
    'schemaVersion' => '1',
    'pageCount'     => 2,
    'locale'        => 'da_DK',
    'size'          => 'a5',
    'format'        => 'flat',
    'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
    // Nonce only when mode requires product AJAX (product mode)
]
```

**Refactor `wp_enqueue_scripts`:**

```php
public function wp_enqueue_scripts() {
    // cart-pdf assets — unchanged
    if ( is_product() ) {
        $product = $this->get_product();
        if ( $product->active ) {
            Editor_Asset_Loader::enqueue( [
                'mode'       => 'product',
                'product_id' => (int) get_the_ID(),
            ] );
        }
    }
}
```

**Adapter method:**

```php
public function enqueue_editor_assets( array $context = [] ): void {
    Editor_Asset_Loader::enqueue( $context );
}
```

### 5.3 `Editor_Renderer::render( array $state, array $context ): string|\WP_Error`

1. Resolve `$product_id` from `$context['product_id']` or `$context['template_id']` — **never** `global $post` alone.
2. `new BPP_Product( $product_id )`; return `WP_Error` if inactive.
3. Apply `$state['field']` via `$product->update_fields()` when present.
4. Generate `$instance_id` (same as asset loader when both called in one request — pass via context or static request cache).
5. Emit scoped wrapper:

```html
<div class="bpp-editor-root"
     data-bpp-instance-id="bpp-editor-…"
     data-bpp-mode="project_edit"
     data-bpp-product-id="456">
  <!-- fonts CSS -->
  <!-- customizer-area markup (pages, thumbnails) -->
  <!-- field form via render_product_customizer_form() -->
</div>
```

6. Hooks: `do_action( 'bpp/before_editor_render', $state, $context )` … `bpp/after_editor_render`.

**Product page BC:** `content_single_product()` becomes thin wrapper calling `Editor_Renderer::render()` with `mode=product`, preserving existing IDs (`#customizer-area`, `#working_div`) **only when mode=product**.

**Project modes:** use class-based selectors scoped under `[data-bpp-instance-id]`; duplicate IDs forbidden.

### 5.4 Theme helper guards

In `class-bpp-woo-cart-functions.php` (and any editor render path):

```php
if ( function_exists( 'ks_render_custom_field_meta' ) ) {
    $meta_items = ks_render_custom_field_meta( $fields, $custom_fields );
}
```

Same for `get_product_min_order_quantity()`.

---

## 6. JavaScript implementation checklist

### 6.1 New module: `assets/js/bpp-editor-runtime.js`

| Export | Purpose |
|--------|---------|
| `getEditorInstance( instanceId )` | Registry lookup |
| `emitBppEvent( name, detail, instanceId )` | `CustomEvent` dispatcher on `document` |
| `collectCanonicalState( instanceId )` | Serialize `field[]` / `page[]` for project save |
| `initEditorRuntime()` | Read `BPP_EDITOR_CONTEXT`, register instance, emit `bpp:editor-ready` |

### 6.2 CustomEvents (document target)

| Event | Detail payload (minimum) |
|-------|--------------------------|
| `bpp:editor-ready` | `{ instanceId, productId, schemaVersion, pageCount, mode }` |
| `bpp:state-loaded` | `{ instanceId, stateVersion }` |
| `bpp:state-changed` | `{ instanceId, fieldUuid?, pageIndex? }` |
| `bpp:validation-failed` | `{ instanceId, code, message }` |
| `bpp:save-requested` | `{ instanceId, state: { field, page, schema_version } }` |
| `bpp:save-completed` | `{ instanceId, stateVersion }` — emitted by OI after REST save |
| `bpp:preview-generated` | `{ instanceId, html? }` |
| `bpp:generation-failed` | `{ instanceId, code, message }` |
| `bpp:image-uploaded` | `{ instanceId, fieldUuid, mime }` |

**Preserve:** jQuery `page-change` on product pages.

### 6.3 Mode-specific save behavior

**`customizer-product.js` changes:**

```javascript
// Constructor: resolve cart button only in product mode
if ( BPP_EDITOR_CONTEXT?.mode === 'product' ) {
  this.cart_btn = document.querySelector('.single_add_to_cart_button');
}

// New method projectSave()
projectSave() {
  const state = collectCanonicalState( this.instanceId );
  emitBppEvent('bpp:save-requested', { instanceId, state }, this.instanceId);
}

// addToCart(): early return in project_edit — must NOT click cart button
if ( BPP_EDITOR_CONTEXT?.mode !== 'product' ) {
  return this.projectSave();
}
```

**`acceptCustomOrder` in `customizer-public.js`:** branch on `BPP_EDITOR_CONTEXT.mode` before `addToCart()`.

### 6.4 Instance scoping

Replace bare `document.getElementById('working_div')` with:

```javascript
const root = document.querySelector(`[data-bpp-instance-id="${instanceId}"]`);
const workingDiv = root?.querySelector('.bpp-working-div') ?? document.getElementById('working_div');
```

Product mode keeps `#working_div` id; project modes use class `.bpp-working-div`.

### 6.5 Webpack

- Import `bpp-editor-runtime.js` from `public.js`.
- Run `npm run build` → update `dist/js/public.dist.js`.

---

## 7. Online Invitations consumer contract (this repo, Prompt 7+)

```php
$adapter = apply_filters( 'bpp/integration/service', null );
if ( ! $adapter instanceof \BPP\Integration\Builder_Adapter_Interface ) {
    // controlled dependency error
}

$context = [
    'source'      => 'online_invitation',
    'mode'        => 'project_edit',
    'user_id'     => get_current_user_id(), // server only — not localized
    'project_id'  => $project_id,
    'product_id'  => $project->product_id,
    'template_id' => $project->template_id,
    'locale'      => $project->locale,
    'size'        => $project->size,
    'format'      => $project->format,
    'state_version' => $project->state_version,
];

$state = $adapter->load_state( [ 'state' => $stored_state ] + $context );
$adapter->enqueue_editor_assets( $context );
echo $adapter->render_editor( $state, $context );
```

**Browser listener (OI `assets/build/project-editor.js`, later):**

```javascript
document.addEventListener('bpp:save-requested', async (e) => {
  // POST to OI REST with nonce; never trust project_id from event alone
});
```

---

## 8. Tests (`pdf-plugin`)

| Test | Type | Assertion |
|------|------|-----------|
| `Editor_Asset_Loader` enqueues `bpp-public-js` when `is_product()` false and context valid | Unit | Script handle registered |
| `enqueue_editor_assets` does not require `global $post` | Unit | Uses `product_id` from context |
| `render_editor` returns error for inactive template | Unit | `WP_Error` |
| `BPP_EDITOR_CONTEXT` export excludes forbidden keys | Static | PHPUnit or script grep |
| Product-page regression: `is_product()` path still enqueues | Unit | Existing gating test |
| No circular `bpp/integration/service` call from renderer | Unit | Adapter does not re-discover self |

---

## 9. Manual browser checks (required before Prompt 7)

| # | Check | Pass criteria |
|---|-------|---------------|
| M1 | Single product customizer | Customize → preview → add to cart unchanged |
| M2 | Folded invitation turn.js | Page turn + PDF preview on product page |
| M3 | Fake My Account shell page | Adapter renders editor; no `.single_add_to_cart_button` click |
| M4 | `project_edit` save button | Dispatches `bpp:save-requested`; network shows **no** `save_cart_pdf` |
| M5 | Multiple editor instances | Two `data-bpp-instance-id` roots do not cross-update |
| M6 | Mobile viewport | Editor scales in non-product container |
| M7 | Console | No errors; `bpp:editor-ready` fires once per instance |

Document results in `pdf-plugin/docs/manual-regression-checklist.md` when implemented.

---

## 10. Definition of done

- [ ] `Editor_Asset_Loader` extracted; `is_product()` path preserved
- [ ] `enqueue_editor_assets( $context )` works outside product pages
- [ ] `render_editor( $state, $context )` scoped with `instance_id`
- [ ] `project_edit` emits `bpp:save-requested`; no add-to-cart
- [ ] Legacy globals (`acceptCustomOrder`, `BPP_PUBLIC_OBJ`) preserved on product pages
- [ ] Theme helper `function_exists` guards added
- [ ] PHPUnit + `npm run build` pass
- [ ] Manual checks M1–M7 recorded

---

## 11. Dependency order

```text
Prompt 4 (adapter interface) → Prompt 5 (this spec) → Prompt 6 (public HTML) → Prompt 7 (OI plugin scaffold)
```

Prompt 5 does **not** implement My Account UI, project storage, or the Online Invitations plugin.
