# My Account section design plan

**Plugin:** `prikogstreg-online-invitations`  
**Scope:** Visual and UX polish for each project tab inside the themed sidebar layout  
**Last updated:** 14 July 2026

---

## Shared foundation (do first)

All sections currently share minimal markup: `<h3>`, raw `<p>` + `<label><br/><input>`, and unstyled WooCommerce tables. Before tab-specific work, introduce a **shared section shell** and **form field system**.

| Component | Class / pattern | Goal |
|-----------|-----------------|------|
| Section shell | `.pks-oi-section` | Consistent page title, intro copy, max-width, spacing below sidebar |
| Section header | `.pks-oi-section__header` | Title + optional status badge + short helper text |
| Stat row | `.pks-oi-stats` | Horizontal summary chips (guests, RSVP, photos, etc.) |
| Form grid | `.pks-oi-form-grid` | Two-column field layout on desktop, single column on mobile |
| Field | `.pks-oi-field` | Label, control, `.pks-oi-field__hint`, `.pks-oi-field__error` |
| Field group | `.pks-oi-field-group` | Visual card grouping related inputs (e.g. “When”, “Where”) |
| Sticky actions | `.pks-oi-form__actions--sticky` | Primary save/publish bar at bottom of long forms |
| Data table | `.pks-oi-table` | Replace bare `shop_table` — zebra rows, badge cells, action column |
| Empty state | `.pks-oi-empty-state` | Icon, headline, body, CTA when section has no data |
| Status badge | `.pks-oi-status-badge--{status}` | Human labels + colour for RSVP, publication, moderation |

**Remove from all templates (sidebar active):** duplicate `pks_oi_render_section_nav()` inline nav — already skipped when sidebar renders.

**Remove from Overview:** duplicate project `<h2>` when sidebar shows project title (optional — keep one source of truth).

---

## Setup

### Overview (`project-overview.php`)

**Current state:** Raw `<dl>` meta, plain checklist bullets, single CTA button. Functional but reads like a debug screen (`active`, `unpublished` slugs).

**Design goals:**
- [ ] Replace raw status strings with coloured badges (Active, Draft, Published, etc.)
- [ ] Turn checklist into **clickable cards** linking to each incomplete step (Design, Event, Guests, Publish)
- [ ] Add **stat summary row** at top (guests, responses, publication) synced with sidebar meta
- [ ] Format dates human-readable (expires, order link)
- [ ] Hero **“Continue setup”** CTA with context (“Next: add event date”)
- [ ] Collapse or remove redundant setup checklist once sidebar shows progress (or keep as compact “quick links”)

---

### Design (`project-design.php`)

**Current state:** Builder embed only; error/save states minimally styled.

**Design goals:**
- [ ] Section intro: what guests will see, autosave behaviour
- [ ] Full-width editor container with subtle border/background separation from account shell
- [ ] Prominent save status bar (saving / saved / conflict) — sticky below editor toolbar if possible
- [ ] Clear empty state when builder inactive (testing mode) with link to support / product setup
- [ ] Mobile: horizontal scroll hint for wide canvas

---

### Event (`project-event.php`)

**Current state:** Long single-column form, 10+ fields stacked, no grouping, `datetime-local` only.

**Design goals:**
- [ ] Group fields into cards: **Basics** (title, organiser), **When** (start, end, timezone, RSVP deadline), **Where** (venue, address), **Extra** (practical info, contact email)
- [ ] Two-column grid for date fields on desktop
- [ ] Required indicators on title + at least one date
- [ ] Field hints (timezone example, RSVP deadline purpose)
- [ ] Sticky **Save event details** bar on scroll
- [ ] Read-only summary view when `!$can_edit`

---

### Guests (`project-guests.php`)

**Current state:** Summary line, CSV import, add form, table, bulk forms, and per-guest inline forms all on one page — busy and hard to scan.

**Design goals:**
- [ ] Top **stat cards**: total guests, attending, opened, not sent
- [ ] **Add guest** in collapsible panel or side drawer (not always visible)
- [ ] **CSV import** as upload card with template download link
- [ ] Guest table: RSVP + invitation status as coloured badges; row actions dropdown (Edit, Copy link, Address book)
- [ ] Bulk action toolbar fixed above table when rows selected
- [ ] Empty state: “No guests yet” + CTA to add or import
- [ ] Remove duplicate per-guest inline forms below table (fold into row actions)
- [ ] Pagination styling consistent with Responses

---

### Address book (`project-address-book.php`)

**Current state:** Search, add form, table, add-to-project — functional but flat.

**Design goals:**
- [ ] Explain optional nature (“Reuse contacts across invitations”)
- [ ] Search as full-width field with icon
- [ ] Contact list as cards on mobile, table on desktop
- [ ] **Add contact** collapsible form
- [ ] Prominent **Add selected to this project** when checkboxes active
- [ ] Empty state when no contacts + CTA to create first
- [ ] Show count in section header matching sidebar meta

---

## Launch

### Preview (`project-preview.php`)

**Current state:** Note + HTML dump in bordered box.

**Design goals:**
- [ ] Device-style frame (phone/desktop toggle optional) around preview HTML
- [ ] Toolbar: “Draft preview” badge, link to Design, link to Publish
- [ ] Empty state when no preview HTML yet
- [ ] Envelope preset as subtle meta chip
- [ ] Full-width toggle for large designs

---

### Publish (`project-publish.php`)

**Current state:** Status text + two buttons + demo form.

**Design goals:**
- [ ] **Pre-publish checklist** visual gate (design ✓, event ✓, guests optional) — disable CTA with inline reasons
- [ ] Published state: success panel + copy public link (when available)
- [ ] Unpublished state: large primary **Publish invitation** button
- [ ] Demo-to-self in secondary card with email icon + rate-limit note
- [ ] Danger styling only for unpublish (not confused with delete)

---

## Manage

### Responses (`project-responses.php`)

**Current state:** Summary line, select filter, CSV export, table, history list.

**Design goals:**
- [ ] Stat pills: attending / declined / pending / opened (clickable filters)
- [ ] Replace `<select>` with filter chips
- [ ] RSVP column: coloured badges not raw slugs
- [ ] Empty state differentiated: no guests vs no responses yet
- [ ] Recent changes as timeline (not bullet list of event types)
- [ ] Export button in header action row

---

### Wishlist (`project-wishlist.php`)

**Current state:** Settings form, add item form, table — dense.

**Design goals:**
- [ ] **Settings card**: toggles as switch UI, external URL field with Ønskeskyen hint
- [ ] **Add gift** collapsible panel
- [ ] Item list as cards with image thumbnail, reserved progress bar, edit/delete
- [ ] Empty state: enable internal wishlist CTA
- [ ] Optional badge in header when disabled

---

### Photos (`project-photos.php`)

**Current state:** Text filter links, table only, no thumbnails.

**Design goals:**
- [ ] Filter tabs styled consistently (pending count badge)
- [ ] **Thumbnail grid** for pending review; table fallback for metadata-heavy view
- [ ] Quick approve/reject on hover or card footer
- [ ] Empty state per filter (no pending / no approved)
- [ ] Pending count in section header (matches sidebar attention state)

---

### Settings (`project-settings.php`)

**Current state:** Archive/restore buttons + danger zone delete — adequate structure, needs polish.

**Design goals:**
- [ ] Split into cards: **Archive**, **Restore**, **Delete permanently**
- [ ] Archive: neutral warning icon + what stops (sends, public link)
- [ ] Delete: existing danger zone — add icon, clearer confirmation field styling
- [ ] Read-only project meta footer (project ID, created date) for support

---

## Suggested implementation order

1. Shared form field + section shell SCSS (`account.scss`, optional `_section-shell.php` partial)
2. **Event** — highest friction (most fields, first setup step after design)
3. **Guests** — most complex interaction
4. **Overview** + **Publish** — guide the setup funnel
5. **Responses**, **Address book**, **Wishlist**, **Photos**
6. **Preview**, **Design** (builder-specific), **Settings**

---

## Files typically touched per section

| Section | Template | Styles |
|---------|----------|--------|
| All | `templates/myaccount/_section-shell.php` (new) | `assets/src/scss/account.scss`, `assets/src/scss/_forms.scss` (new) |
| Per tab | `templates/myaccount/project-{section}.php` | Section-specific BEM blocks |

---

## Acceptance criteria (each tab)

- [ ] Section readable in &lt; 3 seconds — clear title, purpose, primary action
- [ ] All inputs use `.pks-oi-field` with visible labels and focus states
- [ ] Empty and error states designed (not blank page)
- [ ] Mobile: no horizontal overflow except intentional (design editor, preview)
- [ ] Status values shown as human labels + colour, not database slugs
- [ ] Primary action obvious without scrolling on typical viewport
