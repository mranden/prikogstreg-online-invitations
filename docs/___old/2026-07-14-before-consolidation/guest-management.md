# Guest management and address book

**Status:** Implemented in Prompt 16

---

## Guests (`My Account → project → Guests`)

- Unlimited guests per project (no capacity checks)
- Paginated list with RSVP/invitation status summary
- Add, edit, archive, restore
- Independent high-entropy personal token per guest (hash stored only)
- Copy/regenerate personal link (shown once via flash after create/restore/rotate)
- Bulk archive selection
- CSV export with formula-injection neutralization
- CSV import with preview, validation, 500-row limit, and result report
- E-mail optional (copy-link-only guests)
- Duplicate e-mail allowed with UI warning
- Archive revokes public token access; restore issues new token

Fields: `display_name`, `email`, `phone`, `party_label`, `attendee_count`

---

## Address book (`My Account → project → Address book`)

- Private to project owner `user_id`
- Paginated searchable list
- Create, edit, archive, delete contacts
- Add selected contacts to current project (snapshot copy)
- Explicit “Save guest to address book” action
- Normalized e-mail hash for cautious duplicate detection (never merge by name alone)
- Support staff access logged as `address_book_support_view` event

---

## Services

| Service | Role |
|---------|------|
| `GuestService` | CRUD, list, archive, restore, link regeneration |
| `GuestTokenService` | Rotate, revoke, restore tokens |
| `GuestCsv` / `GuestImportService` | Export/import |
| `AddressBookService` | Contact CRUD, project snapshots, support audit |

---

## Tests

```bash
composer test
```

Coverage: cross-user isolation, token uniqueness, duplicate e-mail, archive revoke, CSV injection, import limits, snapshot independence, unlimited guests.
