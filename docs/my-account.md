# My Account application shell

**Status:** Prompts 13–17 — shell, design lifecycle, guests, address book, responses

---

## Endpoint

| URL | Handler |
|-----|---------|
| `/my-account/online-invitations/` | Project list |
| `/my-account/online-invitations/{id}/` | Project overview |
| `/my-account/online-invitations/{id}/{section}/` | Section |

WooCommerce endpoint slug: `online-invitations`

Rewrites flush via `Endpoints::maybe_flush_rewrites()` on activation or when `pks_oi_myaccount_rewrite_version` changes — **not** on every request.

---

## Theme integration filters

Registered by `MyAccount\AccountPresentation` when My Account boots:

| Filter / function | Purpose |
|-------------------|---------|
| `pks_oi_user_project_count` | Active, non-deleted project count for a user ID |
| `pks_oi_get_user_project_count( $user_id = 0 )` | Helper wrapper |

Themes should consume these filters rather than querying `pks_oi_*` tables directly. See the Prikogstreg theme contract: `docs/my-account-online-invitations.md` in the parent theme (when present).

---

## Sections (tabs)

| Section | Status |
|---------|--------|
| overview | Checklist + meta |
| design | Builder editor (`project_edit` mode) + REST save |
| event | POST/Redirect/GET form |
| preview | Draft HTML, no open tracking |
| publish | Publish / unpublish / demo-to-self |
| guests | Guest list, CSV, personal links |
| address-book | Private contacts, add to project |
| responses | RSVP totals, filter, export, recent history |
| settings | Placeholder shell |
| wishlist | Item CRUD, external Ønskeskyen URL, reservation counts (`docs/wishlist.md`) |
| photos | Pending moderation, approve/reject/download/delete (`docs/photo-uploads.md`) |

---

## Design save flow

1. `ProjectController` loads canonical state and calls adapter `render_editor()` + `enqueue_editor_assets()` with `mode: project_edit`.
2. Template exposes REST URL, nonce, and `state_version` on `#pks-oi-editor`.
3. `assets/src/js/account.js` listens for `bpp:save-requested` and POSTs to:

```
POST /wp-json/prikogstreg-online-invitations/v1/projects/{id}/state
```

Body: `{ "expected_state_version": N, "state": { ... } }`

Responses: `200` with new version, `409` stale version, `422` invalid state.

Save never triggers add-to-cart.

---

## REST API (authenticated)

Namespace: `prikogstreg-online-invitations/v1`

| Route | Action |
|-------|--------|
| `POST /projects/{id}/state` | Save design |
| `POST /projects/{id}/event` | Save event JSON |
| `POST /projects/{id}/publish` | Publish snapshot |
| `POST /projects/{id}/unpublish` | Unpublish |
| `POST /projects/{id}/demo` | Demo-to-self (429 rate limit) |

All routes require logged-in owner (or edit entitlement) and WordPress REST nonce.

---

## Authorization

`Security\Authorization`:

- Owner with `pks_oi_manage_own_projects` may view own projects
- Staff with `pks_oi_support_projects` may view any project (support banner shown)
- Edit/publish requires `ProjectEntitlement::can_edit_project()`
- Unauthorized or missing projects return the same not-found message

---

## Domain services

| Service | Responsibility |
|---------|----------------|
| `ProjectStateService` | Load/save canonical builder state |
| `ProjectEventService` | Allowlisted event fields, timezone → UTC |
| `ProjectPreviewService` | Draft preview, `track_opens = false` |
| `ProjectPublishService` | Publish/unpublish + `PublishedHtmlSanitizer` |
| `DemoInvitationService` | Owner demo e-mail hook, 5‑min rate limit |

---

## Templates

Resolution order (allowlisted names only):

1. `child-theme/prikogstreg-online-invitations/{template}.php`
2. `parent-theme/prikogstreg-online-invitations/{template}.php`
3. `plugin/templates/{template}.php`

See [guest-management.md](./guest-management.md) for guest CSV and address-book behaviour.

---

## Browser QA

See [manual-test-design-editor.md](./manual-test-design-editor.md).

---

## Tests

```bash
composer test
```

Coverage includes: router, authorization, project lifecycle (save, stale conflict, publish sanitizer, preview tracking, demo rate limit).
