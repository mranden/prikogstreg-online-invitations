# Storage, publishing, and public rendering

**Last verified:** 2026-07-14

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
2. `ProjectStateService::load_canonical_state`
3. `render_public_pages()`:
   - Adapter path: `render_public_html()` → single page index 1
   - Fallback: each `state['page']` entry → separate published page
4. `PublishedHtmlSanitizer::sanitize` per page
5. `ProjectStorage::publish_snapshot` → `pages/published/page-NNN.html`
6. `PublishedPosterAssetSnapshotter::snapshot` — dimensions + CSS files
7. DB update: `publication_status=published`, `published_version`, timestamps

**Unpublish:** Sets `publication_status=unpublished` (files retained).

---

## Published HTML sanitizer

`Security\PublishedHtmlSanitizer` blocks:

- `script`, `iframe`, `object`, `embed`, `form`, `input`, `button`, `link`, `meta`, `base`, `svg`
- `javascript:`, `vbscript:`, `on*` attributes
- `expression()`, `@import`, `data:text/html`, `-moz-binding`, `behavior:`

Throws `published_html_unsafe` — publish/load aborts.

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
5. `OpenTracker::maybe_track` — personal links only
6. Build wishlist/photo REST context
7. `EnvelopeViewModel` + template `public/invitation.php`

**Unavailable:** Uniform 404 page (`public/unavailable`) — no ID leakage.

---

## Poster viewport

`templates/public/poster.php`:

- Wrapper `.pks-oi-poster-viewport` with design width/height from `PosterDimensions` / poster manifest
- Pages as `.pks-oi-poster-page` — only one visible when multiple
- Navigation: “Page X of Y”, prev/next, keyboard arrows
- `assets/src/js/public.js` scales canvas to fit without horizontal overflow

**CSS/JS:** No BPP editor bundles on public. Display/fonts from snapshotted project files or runtime BPP fallback if snapshot missing (legacy publishes).

---

## Envelope animation

`templates/public/envelope.php`:

- Closed envelope card (preset CSS + optional image with dimensions)
- Accessible open button (`aria-expanded`, keyboard)
- `prefers-reduced-motion` bypass in `public.js`
- Content region: poster + sections (RSVP, wishlist, photos)

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

Verified by `PublicPosterExperienceTest::test_poster_assets_snapshot_without_pdf_plugin`.

---

## Multi-page published invitations

When multiple pages exist in `published/manifest.json`:

- Loader returns structured `PublicInvitationContent` (ordered pages)
- UI shows page controls; does not stack all pages

When adapter publish path used: typically **one** combined published page (V1 limitation — document, do not silently drop without manifest entry).
