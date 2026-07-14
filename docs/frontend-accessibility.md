# Frontend accessibility

**Status:** V1 manual checklist + automated template checks  
**Scope:** `.pks-oi`, `.pks-oi-public`, `.pks-oi-admin*` roots only — no global theme overrides.

---

## Manual checklist (release QA)

### Envelope / public invitation

- [ ] “Open invitation” is keyboard reachable and activates with Enter/Space
- [ ] `prefers-reduced-motion: reduce` reveals content without animation
- [ ] `<noscript>` shows invitation content when JavaScript is disabled
- [ ] Published canvas scales on phone/tablet without horizontal page scroll
- [ ] Page works at 200% browser zoom without loss of function

### Forms (RSVP, wishlist, photos, My Account)

- [ ] Every input has a visible `<label>` or `<legend>`
- [ ] Required fields are indicated in label text (not colour alone)
- [ ] Inline status regions use `role="status"` + `aria-live="polite"`
- [ ] Errors use text prefix (⚠) and `.is-error` — not colour alone
- [ ] Upload shows busy state (`aria-busy`) while in flight
- [ ] Focus ring visible on keyboard navigation (`:focus-visible`)

### My Account

- [ ] Section nav exposes `aria-current="page"` on active item
- [ ] Tables degrade on narrow screens (stacked rows)
- [ ] Touch targets ≥ 44×44 CSS px on primary actions

### Admin support

- [ ] Support form labels associated with inputs
- [ ] Status indicators include text/icon prefix

### E-mail

- [ ] Plain-text templates include invitation/account URL on its own line
- [ ] HTML templates use semantic headings and alt text where images exist

---

## Automated checks

`tests/Integration/Frontend/AccessibilityTest.php` verifies:

- Allowlisted templates resolve
- Public envelope template includes noscript fallback and focus target
- RSVP form includes labels and `aria-live` status
- Wishlist/photo status regions include `aria-live`
- Built CSS/JS exist under `assets/build/` without `.map` files

---

## Implementation notes

- Styles live in `assets/src/scss/` and compile to `assets/build/css/`
- Scripts use esbuild without source maps in production (`package.json`)
- JavaScript strings for async UX are localized via `wp_localize_script` with text domain `prikogstreg-online-invitations`
