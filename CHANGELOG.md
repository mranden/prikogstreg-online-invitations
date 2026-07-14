# Changelog

All notable changes to **Prikogstreg Online Invitations** are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/). Versioning aligns with `PKS_OI_VERSION` in `prikogstreg-online-invitations.php`.

## [0.1.0] — 2026-07-14

### Added

- WooCommerce **Online invitation** product type and checkout integration (HPOS-compatible order reads).
- Post-purchase project creation with PDF Builder state import from order item storage.
- Private project file storage (`StoragePath`, atomic writes, checksum verification, manifest).
- My Account endpoint `online-invitations` with design editor, event, guests, address book, delivery, wishlist, photos, settings.
- Authenticated REST API (`prikogstreg-online-invitations/v1`) for state, event, publish/unpublish, demo invitation.
- Public rewrite route `/invitation/{token}/` with animated invitation rendering and open tracking.
- Public REST routes for RSVP, wishlist reserve/release, and guest photo upload (token-gated).
- Guest management: CSV import, per-guest tokens, generic token, rotation/revocation.
- Delivery queue with Action Scheduler (invitations, reminders, welcome, batch processing).
- WooCommerce e-mail classes: welcome, guest invitation, demo, RSVP confirmation/reminder, organiser RSVP, photo notification.
- Wishlist with guest reservations and private image storage.
- Guest photo uploads with intent HMAC, validation, moderation, and rate limiting.
- Admin support screen: import retry, expiry override, token rotation, restriction/archive/restore, hard delete.
- Refund and order-status listeners restricting delivery and public actions.
- Project expiration scheduler and retention cleanup (temp files, event/delivery log pruning).
- Database schema v1 (8 tables) via `dbDelta` migrator.
- Frontend assets (account, public, admin) with scoped SCSS, accessibility patterns, theme override hooks.
- Partial Danish translation (`languages/prikogstreg-online-invitations-da_DK.po`).
- PHPUnit suite: 249 tests, 784 assertions (unit, integration, PHP E2E chain).
- Documentation set under `docs/` including security, performance, and data-integrity reviews.

### Security

- IDOR/nonce/SQL-injection negative tests; published HTML sanitizer at publish and load.
- Photo upload intent signing; path traversal protection; CSV injection neutralisation.
- Token hashing at rest with rotation support.

### Dependencies

- Runtime: PHP 8.1+, WordPress 6.5+, WooCommerce 8.0+, Action Scheduler, PDF Builder with `bpp/integration/service` filter.
- Build: Node (esbuild, sass) for assets; Composer for PSR-4 autoload only (no runtime Composer packages).

### Known gaps (non-blocking for code release)

- Browser E2E and cross-client e-mail testing require staging.
- Checkout Blocks unsupported.
- Full `.pot` generation and complete Danish translation pending.
- Production private storage path must be verified on hosting.

### Related

- PDF Builder 0.1.x: `BPP_Ajax_Security` hardening (Prompt 25).
- PDF Builder Integration adapter: `pdf-plugin/src/Integration/` (Prompt 27).

[0.1.0]: https://prikogstreg.dk/
