# Wishlist and reservations

**Status:** Implemented in Prompt 19

---

## Owner (My Account)

Path: `/my-account/online-invitations/{id}/wishlist/`

- Optional external **Ønskeskyen** URL (`external_wishlist_url`) — HTTP/HTTPS only, no scraping/sync
- Toggle internal wishlist (`internal_wishlist_enabled`)
- **Surprise privacy:** `show_reserver_identity` defaults to off — organiser sees counts only
- Internal item CRUD: title, description, optional product/image URL, quantity requested, sort order, active/hidden
- Reorder via posted `wishlist_item_ids[]`
- Reservation counts per item; reserver names only when identity setting is enabled

---

## Public (invitation page)

- Active items only (hidden/archived excluded)
- External wishlist link when configured
- Personal token guests reserve/release via REST
- Generic-link visitors must provide a display name on first reserve (creates guest context)
- Guests see availability and their own reservation quantity — never other guests' identities
- Repeat reserve/release with the same idempotency key is safe

### REST

```
GET  /wp-json/prikogstreg-online-invitations/v1/public/{token}/wishlist
POST /wp-json/prikogstreg-online-invitations/v1/public/{token}/wishlist/{item_id}/reserve
POST /wp-json/prikogstreg-online-invitations/v1/public/{token}/wishlist/{item_id}/release
```

Header: `X-PKS-OI-Idempotency-Key` (recommended)

---

## Atomic reservations

Table: `pks_oi_wishlist_reservations` with unique `(wishlist_item_id, guest_id)`.

- `quantity_reserved` on `pks_oi_wishlist_items` updated via conditional increment (`try_adjust_reserved`)
- One active reservation row per guest per item; quantity can be 1..remaining
- Simultaneous final-item attempts: second caller gets `insufficient_quantity`
- Restricted/unpublished/expired projects reject mutations

---

## Key classes

| Class | Role |
|-------|------|
| `WishlistItemService` | Owner CRUD, settings, reorder |
| `WishlistReservationService` | Public list, reserve, release |
| `WishlistSanitizer` | Text and URL validation |
| `WishlistController` (Public) | REST endpoints |
| `WishlistController` (My Account) | Owner UI |

---

## Tests

`tests/Integration/Wishlist/WishlistTest.php` covers URL validation, CRUD, race, multi-quantity, idempotency, release, hidden items, privacy, invalid token, restricted project, and XSS stripping.
