# My Account, guests, RSVP, wishlist, and photos

**Last verified:** 2026-07-14

---

## My Account endpoint

**Slug:** `online-invitations` (`MyAccount\Endpoints::SLUG`)  
**URLs:**

- `/my-account/online-invitations/` — project list
- `/my-account/online-invitations/{project_id}/` — overview (default)
- `/my-account/online-invitations/{project_id}/{section}/` — section

Registered via `MyAccountRegistrar` + `woocommerce_account_online-invitations_endpoint`.

---

## Sections (`ProjectSections`)

| Section | Slug | Controller / template |
|---------|------|----------------------|
| Overview | `overview` | `ProjectController` |
| Design | `design` | Design editor (adapter) |
| Event | `event` | Event form |
| Guests | `guests` | `GuestController` |
| Address book | `address-book` | `AddressBookController` (hidden from nav) |
| Preview | `preview` | Preview service |
| Publish | `publish` | Publish/unpublish actions |
| Responses | `responses` | `ResponsesController` |
| Wishlist | `wishlist` | `WishlistController` |
| Photos | `photos` | `PhotoController` |
| Settings | `settings` | Project settings |

**Authorization:** `Security\Authorization` — project owner or cap `pks_oi_support_projects` / `view_online_invitation_projects`.

---

## Admin support relationship

Shop managers and administrators use **Online Invitations → Invitations** (`pks-online-invitations`) for the same underlying project/guest/RSVP/photo data as My Account.

| My Account section | Admin detail tab | Safe support edits |
|--------------------|------------------|-------------------|
| Event | Event details | Event title, dates, venue, practical info, RSVP deadline via `AdminSupportService` → `ProjectEventService::save_event_details_for_support()` |
| Guests | Guests & RSVP | Guest name/email, RSVP status, attendee count via `AdminSupportService` |
| Photos | Photos | Approve/reject via `PhotoService::moderate()` + admin audit |
| Publish | Tools | Publish/unpublish/restrict/restore via existing `ProjectSupportActions` (POST + nonce) |
| Wishlist | Wishlist | View-only summary in admin V1 |

**Photo moderation (global):** **Online Invitations → Photos** lists pending uploads across projects; row action links to project Photos tab.

Customer My Account behaviour is unchanged — admin edits go through the same domain services with separate audit actor typing.

**Assets:** `assets/build/css/account.css`, `account.js` enqueued on endpoint.

---

## Authenticated REST (`prikogstreg-online-invitations/v1`)

`ProjectRestController` (cookie + `wp_rest` nonce):

| Method | Route |
|--------|-------|
| POST | `/projects/{id}/state` |
| POST | `/projects/{id}/event` |
| POST | `/projects/{id}/publish` |
| POST | `/projects/{id}/unpublish` |
| POST | `/projects/{id}/demo` |

---

## Guests

**Services:** `GuestService`, `GuestImportService`, `GuestCsv`, `GuestTokenService`

| Feature | Detail |
|---------|--------|
| Manual add / edit | My Account guests section |
| CSV import | Injection-neutralised parsing |
| Personal token | Per guest; rotatable/revokable |
| Delivery queue | Invitation e-mails via `DeliveryQueueService` |
| Generic RSVP guests | `is_generic_response=1` from public generic link |

---

## Address book

`AddressBookService` — per **user**, reusable across projects (not project-scoped).

---

## RSVP

### Public REST

`POST /wp-json/prikogstreg-online-invitations/v1/public/{token}/rsvp`

| Link type | Behaviour |
|-----------|-----------|
| Personal | Update existing guest RSVP |
| Generic | Name + optional email → new guest with personal token |

**Headers:** `X-WP-Nonce`, `X-PKS-OI-Idempotency-Key`  
**Rate limits:** Per-token and per-IP creation limits

### Post-RSVP

Queues `rsvp_confirmation` and `organizer_rsvp` deliveries.

Template: `templates/public/rsvp-form.php` with `aria-live` status region.

---

## Wishlist

**Organiser:** My Account wishlist section — CRUD items, optional images in private storage.

**Public REST:**

- `GET /public/{token}/wishlist`
- `POST /public/{token}/wishlist/{item_id}/reserve`
- `POST /public/{token}/wishlist/{item_id}/release`

Generic links may require display name. External wishlist URL supported via `external_wishlist_url` project field.

---

## Guest photos

Dedicated **photo share page** at `/photos/{share_token}/` — separate from the invitation envelope link.

### Guest flow

1. Guest opens photo share URL (from envelope link, QR, or e-mail invite).
2. Enters **fotokode** (access code) — never embedded in URL, QR, or e-mail body.
3. `POST /wp-json/prikogstreg-online-invitations/v1/photo-share/{token}/verify` issues HttpOnly session cookie `pks_oi_photo_sess` (4h TTL).
4. Upload: `POST .../photos/intent` → `POST .../photos/upload` (multipart, intent header) — same validation and private storage as before.
5. Optional **public gallery** on landing page when organiser enables it: `GET .../gallery` + protected stream `/photos/{token}/stream/{id}/`.

**Envelope:** `templates/public/photos.php` shows a link to the photo page when `guest_photos_enabled` — no inline upload on the invitation.

### Owner (My Account → Photos)

| Feature | Detail |
|---------|--------|
| Settings | Access code, upload close date, public gallery toggle |
| Share tools | Copy link, native share, QR download |
| Moderation | Thumbnail grid, bulk approve/reject, delete |
| E-mail invites | Queues `photo_share_invite` — link + instructions only; owner shares fotokode separately |

**Validation:** `PhotoImageValidator` — MIME, dimensions, size limits  
**Storage:** `photos/pending/` until organiser approves → `photos/approved/`  
**Moderation:** My Account photos section + admin Photos submenu

### Public REST (photo share)

| Method | Route |
|--------|-------|
| POST | `/photo-share/{token}/verify` |
| POST | `/photo-share/{token}/photos/intent` |
| POST | `/photo-share/{token}/photos/upload` |
| GET | `/photo-share/{token}/gallery` |

Session cookie required for intent/upload; gallery respects public toggle + session.

---

## Delivery and reminders

| Scheduler | Purpose |
|-----------|---------|
| `WelcomeScheduler` | Post-purchase welcome |
| `ReminderScheduler` | RSVP reminders before event |
| `DeliveryActionHandler` | Sends queued WC emails |
| `ExpirationScheduler` | Project expiry |

E-mail classes registered in `EmailRegistry` (extends WooCommerce email system).

---

## Demo invitation

`DemoInvitationService` — organiser preview link before publish (entitlement-gated).

---

## Theme integration

- `Sidebar` hooks into Prikogstreg My Account sidebar when in OI context
- `theme-api.php` exposes `pks_oi_get_user_projects_nav()` etc.
- Templates overridable in theme: `{theme}/prikogstreg-online-invitations/`

See `03-architecture-and-responsibilities.md` for theme vs plugin boundary.
