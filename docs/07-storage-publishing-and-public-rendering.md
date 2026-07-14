# Storage, publishing, and public rendering

**Last verified:** 2026-07-14 (production public invitation landing page)

---

## Storage root

Resolved by `StoragePath`:

1. Constructor/test override
2. `PKS_OI_STORAGE_PATH` (recommended production)
3. `{WP_CONTENT_DIR}/uploads/pks-oi-private`
4. System temp fallback (discouraged)

`StorageBootstrap` writes `.htaccess` + `index.php` on fallback root.

---

## Manifests

| File | Class | When written |
|------|-------|--------------|
| `manifest.json` | `ProjectManifest` | State save |
| `published/manifest.json` | `ProjectManifest` | Publish |
| `envelope/manifest.json` | `EnvelopeManifest` | Import |
| `published/poster-manifest.json` | `PublishedPosterManifest` | Publish |

All page files have SHA-256 checksums in manifests. Load fails closed on mismatch.

---

## Publish pipeline

`ProjectPublishService::publish()`:

1. `ProjectEntitlement::can_publish_project` — requires event title, dates, etc.
2. `ProjectStateService::load_state_for_publish` — merges adapter state with editable `pages/editable/page-*.html` when adapter `page[]` is empty
3. `render_public_pages()`:
   - Adapter path: `render_public_html()` with `is_public => true` → single page index 1
   - When adapter HTML is empty: fallback to each `state['page']` entry
   - Rejects publish when all pages lack substantive content (`empty_published_html`)
4. `PublishedHtmlSanitizer::sanitize` per page
5. `ProjectStorage::publish_snapshot` → `pages/published/page-NNN.html`
6. `PublishedPosterAssetSnapshotter::snapshot` — dimensions + CSS files
7. DB update: `publication_status=published`, `published_version`, timestamps

**Unpublish:** Sets `publication_status=unpublished` (files retained).

---

## Published HTML requirements

- Must contain visible text and/or images after sanitization (`Support\PublishedHtmlValidator`)
- Empty BPP wrapper-only snapshots (`<div class="bpp-public-invitation" …></div>`) are rejected at publish and public load
- **Public load failure:** `PublicInvitationLoader` returns `empty_published_html`; guests see uniform unavailable page; project owners/support see actionable republish message (no token/HTML in logs)

---

## Published HTML sanitizer

`Security\PublishedHtmlSanitizer` blocks:

- `script`, `iframe`, `object`, `embed`, `form`, `input`, `button`, `link`, `meta`, `base`, `svg`
- `javascript:`, `vbscript:`, `on*` attributes
- `expression()`, `@import`, `data:text/html`, `-moz-binding`, `behavior:`

Throws `published_html_unsafe` — publish/load aborts.

Poster canvas uses `overflow: hidden` and `isolation: isolate` so untrusted HTML cannot cover trusted RSVP/wishlist controls.

---

## Public routes

Registered in `Public\Endpoints` (`REWRITE_VERSION = 3`):

| URL | Handler |
|-----|---------|
| `/invitation/{token}/` | Full invitation page |
| `/invitation/{token}/envelope-image/` | Stream envelope image |
| `/invitation/{token}/poster-asset/display/` | Stream snapshotted display CSS |
| `/invitation/{token}/poster-asset/fonts/` | Stream snapshotted fonts CSS |

Query vars: `pks_oi_invitation_token`, `pks_oi_envelope_asset`, `pks_oi_poster_asset`.

---

## Public render sequence

`PublicController::maybe_render_invitation()`:

1. Rate limit invalid tokens
2. `TokenResolver` → guest or generic
3. `PublicEntitlement::is_publicly_available`
4. `PublicInvitationLoader::load_published_content` — verified manifest + pages only
5. `OpenTracker::maybe_track` — personal links only (page load)
6. Build wishlist/photo REST context
7. `EnvelopeViewModel` + template `public/invitation.php`

**Unavailable:** Uniform 404 page (`public/unavailable`) — no ID leakage.

---

## Public template structure

`templates/public/invitation.php` — standalone HTML shell (`pks-oi-public-shell` body class).

`templates/public/envelope.php`:

- Envelope stage (`min-height: 100svh`, safe-area padding)
- Letter layer contains the actual published poster HTML for opening animation
- Content region (`#pks-oi-invitation-content`) holds revealed poster + sections
- Section order: event details (when structured data exists) → RSVP → wishlist → photos

Partial: `templates/public/partials/event-details.php` — date, time, venue, address, maps link, practical info from project row.

---

## Envelope state model

Single attribute on `.pks-oi-envelope`:

```text
closed → opening → revealed → settled
```

Implemented as `data-envelope-state` (CSS + `assets/src/js/modules/envelope-controller.js`).

Opening sequence:

1. Open button marked busy; duplicate clicks ignored
2. 3D flap + letter rise (transform/opacity only)
3. Actual poster HTML animates from envelope letter layer
4. Content region unhidden; `inert` removed; `aria-expanded="true"`
5. Stage collapses on `settled`; poster enters normal document flow
6. Optional `sessionStorage` keyed by SHA-256 prefix of token (never stores raw token)

**Reduced motion:** skips 3D motion; reveals content immediately with short opacity transition; open button hidden.

**No JavaScript:** `<noscript>` hides stage, shows `#pks-oi-invitation-content` with poster and sections.

---

## Poster viewport

`templates/public/poster.php`:

- Wrapper `.pks-oi-poster-viewport` with `data-poster-width` / `data-poster-height` and CSS vars
- Scale: `min(1, frameWidth / designWidth)` via transform on canvas (`modules/poster-viewport.js`)
- Recalculates on resize, font load, image load, and envelope reveal
- Multi-page: prev/next, “Side X af Y”, keyboard arrows; nav hidden when only one page

**CSS/JS:** No BPP editor bundles on public. Display/fonts from snapshotted project files or runtime BPP fallback if snapshot missing (legacy publishes).

---

## Public asset isolation

`Public\PublicAssetManager` on invitation route only:

- Dequeues theme shop scripts (minicart, gallery, live search, product JS) and unrelated WooCommerce block/cart assets
- Removes Prikogstreg minicart footer markup via hook inspection
- Filter: `pks_oi/public/dequeue_handles` for site-specific additions
- Does **not** dequeue globally; shop/product routes unchanged

Retains: OI public CSS/JS, poster display/fonts, WordPress admin bar for authenticated admins.

---

## Privacy headers (public)

- `X-Robots-Tag: noindex, nofollow`
- `Cache-Control: private, no-store`

---

## pdf-plugin independence

After publish, public invitations should render with pdf-plugin **deactivated**:

- `published/poster-display.css` copied or generated at publish
- `published/poster-fonts.css` from `BPP_fonts_css()` when available at publish time
- Fallback: `assets/css/bpp-poster-display-fallback.css`

Publish merge fix (`load_state_for_publish`) resolves empty adapter wrappers when editable page files contain HTML — **no pdf-plugin change required** for typical empty-poster cases.

Verified by `PublicPosterExperienceTest::test_poster_assets_snapshot_without_pdf_plugin`.

---

## Multi-page published invitations

When multiple pages exist in `published/manifest.json`:

- Loader returns structured `PublicInvitationContent` (ordered pages)
- UI shows page controls; does not stack all pages

When adapter publish path used: typically **one** combined published page (V1 limitation — document, do not silently drop without manifest entry).
