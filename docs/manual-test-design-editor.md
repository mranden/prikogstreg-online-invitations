# Manual test — design editor in My Account

Use after deploying Prompt 14 changes. Requires PDF Builder adapter active on product pages.

---

## Prerequisites

1. WooCommerce order in a qualifying status with an imported invitation project.
2. Log in as the project owner.
3. Run `npm run build` so `assets/build/js/account.js` is current.

---

## Design section

1. Open **My Account → Online invitations → {project} → Design**.
2. Confirm the builder editor renders (same fidelity as product page where possible).
3. Change text or layout in the editor.
4. Click the builder save control.
5. In DevTools **Network**, confirm:
   - `POST …/wp-json/prikogstreg-online-invitations/v1/projects/{id}/state`
   - Response `200` with incremented `state_version`
   - **No** `save_cart_pdf` or add-to-cart request
6. Reload the page — changes persist.
7. Open a second browser/tab as the same user, save from one tab, then save from the other with a stale version — expect `409` and a user-visible conflict message.

---

## Event section

1. Open **Event** tab.
2. Set event title and start date; submit.
3. Confirm redirect with success notice and data on overview checklist.

---

## Preview

1. Open **Preview** tab.
2. Confirm draft HTML renders.
3. Confirm no open-tracking pixel or guest token in markup (`data-track-opens="0"` on frame).

---

## Publish

1. Complete event details if not already set.
2. Open **Publish** → **Publish invitation**.
3. Confirm success notice and publication status on overview.
4. **Unpublish** — status returns to unpublished; published files remain on disk.
5. **Send demo to myself** — success once; second click within 5 minutes shows rate limit (form still submits; notice depends on handler).

---

## Regression

- Product-page customisation → cart → checkout flow unchanged.
- RSVP/wishlist/photo forms are placeholders only (Prompts 17–20).
