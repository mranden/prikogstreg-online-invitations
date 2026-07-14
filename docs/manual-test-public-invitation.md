# Manual test — public invitation envelope

Use after Prompt 15 with a published project and at least one guest token (or generic link from rotation).

---

## Prerequisites

1. Published invitation project with valid published snapshot.
2. Personal guest token URL: `/invitation/{guest-token}/`
3. Generic project token URL: `/invitation/{generic-token}/`
4. Run `npm run build` for current `public.js` / `public.css`.

---

## Desktop

1. Open a **personal** link in a private/incognito window (not logged in as owner).
2. Confirm envelope shows the guest name.
3. Click **Open invitation** — envelope animates and reveals published HTML.
4. Press Tab to focus the open control; activate with Enter/Space.
5. View page source — confirm `noindex` meta and no `project_id` / `order_id` in HTML.
6. Repeat with **generic** link — neutral addressee text, no guest name.

---

## Mobile

1. Open personal link on phone-width viewport.
2. Confirm invitation scales (`bpp-public-invitation` transform) without horizontal overflow.
3. Open control remains tappable.

---

## Reduced motion

1. Enable **Reduce motion** in OS settings.
2. Reload invitation — content visible without animation; open button hidden.

---

## JavaScript disabled

1. Disable JS in browser.
2. Reload — invitation content visible via `<noscript>` fallback.

---

## Negative cases (uniform message)

| Case | Expected |
|------|----------|
| Random invalid token | “Invitation unavailable” (404) |
| Revoked/archived guest token | Same unavailable page |
| Unpublished project | Same unavailable page |
| Expired project | Same unavailable page |

---

## Open tracking

1. Open personal link as anonymous guest.
2. In database, guest row should have `first_opened_at_utc` and `open_count = 1`.
3. Open same link while logged in as **project owner** — open count should not increment.
4. Generic link — no guest open timestamps change.

---

## Bot/prefetch caveat

Some e-mail clients prefetch URLs. Treat open metrics as “link opened”, not confirmed human read.
