=== Prikogstreg Online Invitations ===
Contributors: prikogstreg
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: Proprietary

WooCommerce online invitation projects integrated with the Prikogstreg PDF Builder.

== Description ==

Customers purchase online invitation products, customise designs on the product page (via PDF Builder), and receive a private invitation project after checkout. Organisers manage guests, delivery, RSVP, wishlists, and photo uploads in WooCommerce My Account. Guests open animated public invitations via opaque token links.

This plugin requires WooCommerce, Action Scheduler (bundled with WooCommerce), and the Prikogstreg PDF Builder (`pdf-plugin`) with the `bpp/integration/service` adapter filter.

== Requirements ==

* PHP 8.1+
* WordPress 6.5+
* WooCommerce 8.0+ (HPOS compatible)
* Action Scheduler (via WooCommerce)
* Prikogstreg PDF Builder with integration adapter
* Private filesystem path outside the web root recommended (`PKS_OI_STORAGE_PATH` in `wp-config.php`)

== Installation ==

1. Upload the plugin folder to `wp-content/plugins/prikogstreg-online-invitations`.
2. In the plugin directory run `composer install --no-dev` (production) or `composer install` (development).
3. Activate through the Plugins screen. Database tables are created on activation.
4. Flush permalinks once (Settings → Permalinks → Save) if public invitation URLs return 404.
5. Ensure PDF Builder is active and exposes `bpp/integration/service`.
6. Optionally define private storage in `wp-config.php`:

`define( 'PKS_OI_STORAGE_PATH', '/var/private/prikogstreg-oi' );`

Compiled CSS/JS are shipped in `assets/build/`. Rebuild after source changes with `npm install && npm run build`.

== Product setup ==

1. Create a WooCommerce product using the **Online invitation** product type (`WC_Product_Online_Invitation`).
2. Assign a PDF Builder template (`_bpp_product` meta via PDF Builder admin).
3. Configure invitation limits on the product (guest cap, delivery options) per product meta documented in `docs/product-type.md`.
4. Theme must render PDF Builder customiser on single product (see `docs/builder-integration.md`).

== Customer flow ==

1. Customer customises invitation on product page (PDF Builder) and adds to cart.
2. Order completes → plugin creates one `pks_oi_project` per qualifying line item.
3. Builder state is imported from order item storage into private project files.
4. Customer receives welcome e-mail (scheduled) with My Account project link.
5. Customer edits design, event details, guests, and delivery in My Account.
6. Customer publishes → guests receive invitation links; public animated page is served at `/invitation/{token}/`.

== My Account ==

Endpoint: `/my-account/online-invitations/` (rewrite slug `online-invitations`).

Per-project sections include overview, design editor, event, guests, address book, delivery, wishlist, photos, and settings. URLs: `/my-account/online-invitations/{project_id}/{section}/`.

See `docs/my-account.md` for section behaviour and authorization.

== Public links ==

* Guest invitation: `/invitation/{opaque_token}/` — per-guest token, RSVP, wishlist, photo upload.
* Generic link: `/invitation/{generic_token}/` — shared link with generic RSVP (when enabled).
* Tokens are opaque, hashed at rest, rotatable from My Account or admin support.

See `docs/public-invitation.md`.

== Guests and address book ==

* CSV import with injection neutralisation.
* Private address book (per user, not per project) for re-use across projects.
* Per-guest invitation status, token rotation, and delivery queue.
* Open tracking when guest loads invitation (privacy-conscious; see `docs/privacy-retention.md`).

See `docs/guest-management.md`.

== RSVP and reminders ==

* Guests submit RSVP via public REST (`POST .../rsvp`).
* Organiser receives notification e-mail; guest receives confirmation.
* Reminders scheduled via Action Scheduler when event date and reminder settings apply.

See `docs/rsvp.md` and `docs/email-delivery.md`.

== Wishlist ==

* Organiser manages wishlist items in My Account.
* Guests reserve/release items on the public invitation via REST.
* Images stored in private project storage.

See `docs/wishlist.md`.

== Photos ==

* Guests request upload intent (HMAC-signed), then upload via REST.
* Server-side validation (MIME, dimensions, size), moderation workflow, organiser notification.
* Rate limits and cleanup of abandoned temp files.

See `docs/photo-uploads.md`.

== Privacy ==

* Data minimisation, retention schedules, hard delete on customer request (where allowed).
* Event logs and delivery logs pruned on schedule.
* No public URLs for private files; streaming only after authorization.
* GDPR-oriented flows documented in `docs/privacy-retention.md` and `docs/lifecycle.md`.

== Build and development ==

Development:

`composer install`
`composer test` (249 PHPUnit tests)
`npm install && npm run build`

Production package:

`composer install --no-dev`
`composer dump-autoload -o`
Include `vendor/` (autoload only — no runtime Composer packages), `assets/build/`, `languages/`, `src/`, bootstrap PHP, `readme.txt`.

Exclude: `node_modules/`, `tests/`, `.phpunit.cache/`, dev-only vendor packages, source maps (none in current build), secrets, customer project data.

Developer reference: `docs/developer-guide.md`. Operations: `docs/operations-runbook.md`.

== Known limitations ==

* Classic WooCommerce checkout only — Checkout Blocks not tested.
* Danish translation partial (`languages/prikogstreg-online-invitations-da_DK.po`); `.pot` not generated in repo.
* No browser E2E automation (Playwright/Cypress) — manual test matrix in `docs/test-plan.md`.
* PDF Builder adapter integration tests use stubs until `pdf-plugin/src/Integration/` ships.
* Published HTML sanitizer blocks script/event handlers; deep pen-test of builder output (`iframe`, SVG) remains manual.
* E-mail rendering across clients requires staging SMTP verification.
* `WC_Product_Online_Invitation` lives outside PSR-4 namespace (Composer skips it; loaded by WooCommerce product factory).

== V2 exclusions (not in this release) ==

SMS, phone verification, guest pricing/capacity upgrades, additional-capacity purchases, custom domains, full event microsite, collaborator accounts, direct Ønskeskyen sync, and direct social publishing.

== Changelog ==

= 0.1.0 =
* V1 online invitations: product type, checkout project creation, My Account editor, public invitations, guests, address book, delivery, open tracking, RSVP/reminders, wishlist, guest photos, admin support, refund restrictions, expiration, privacy cleanup.
* 249 automated tests; security/performance/data-integrity audits (Prompts 24–25).
* PDF Builder AJAX hardening (`BPP_Ajax_Security`).
* See CHANGELOG.md for full release notes.
