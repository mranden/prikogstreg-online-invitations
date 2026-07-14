# Private project file storage

**Status:** Implemented in `src/Storage/` (Prompt 9)  
**Authoritative references:** `docs/technical-plan.md` §8, `docs/architecture-decisions.md` ADR-010, `.cursor/agent.md` §15

---

## Storage root resolution

| Priority | Root | Notes |
|---------:|------|-------|
| 1 | `PKS_OI_STORAGE_PATH` constant in `wp-config.php` | Preferred — outside public web root |
| 2 | `wp-content/uploads/pks-oi-private/` | Fallback with `.htaccess` + `index.php` deny rules |

Set in `wp-config.php`:

```php
define( 'PKS_OI_STORAGE_PATH', '/var/private/prikogstreg-oi' );
```

Implementation: `StoragePath::root()`.

---

## Directory layout

```text
{root}/projects/{storage_uuid}/
├── manifest.json
├── state/
│   ├── current.json
│   └── previous.json
├── pages/
│   ├── editable/page-001.html
│   └── published/page-001.html
├── published/manifest.json
├── previews/
├── wishlist/images/
├── photos/{pending,approved,thumbnails}/
└── tmp/
```

Paths are resolved only from `storage_uuid` and allowlisted relative paths (`StoragePath`). Raw request paths are never accepted.

---

## Core classes

| Class | Responsibility |
|-------|----------------|
| `StoragePath` | Root resolution, UUID validation, traversal-safe path building |
| `AtomicFileWriter` | Temp write, `flock`, `fflush`, SHA-256, atomic `rename()` |
| `ProjectManifest` | `manifest.json` / `published/manifest.json` serialization |
| `ProjectStorage` | Create dirs, save state, publish snapshot, read/verify, delete tree |
| `SafeFileReader` | Read files with optional checksum verification |
| `FileStreamResponse` | Authorized streaming helper (no public URLs) |
| `StorageCleanup` | Abandoned temp cleanup + project tree deletion |
| `StorageDiagnostic` | Admin support health report |
| `StorageRegistry` | Factory wired from `Plugin::storage()` |

---

## Save flow

1. Validate UTF-8 and size limits (`StorageLimits`)
2. Compare `expected_state_version` with manifest `state_version` (conflict → `StorageConflictException`)
3. Copy existing `state/current.json` → `state/previous.json`
4. Atomically write editable page files
5. Atomically write `state/current.json`
6. Update `manifest.json` with page checksums, `state_sha256`, incremented `state_version`
7. Cleanup abandoned `tmp/` files older than 1 hour

Publish writes separate `pages/published/*` and `published/manifest.json` without mutating editable files.

---

## Database contract

The `pks_oi_projects` table stores:

- `storage_uuid`
- `state_version` / `published_version`
- `state_manifest_path` (relative, e.g. `manifest.json`)
- `published_manifest_path` (relative, e.g. `published/manifest.json`)

No raw HTML or large JSON blobs are stored in custom tables.

---

## Limits

| Item | Limit |
|------|------:|
| `state/current.json` | 16 MiB |
| Page HTML file | 5 MiB |
| Manifest JSON | 128 KiB |
| Temp file retention | 1 hour |

---

## Tests

```bash
composer test
```

Coverage includes: directory creation, atomic save, simulated partial failure, stale version conflict, checksum mismatch, traversal rejection, invalid UTF-8, oversized state, previous-state recovery, publish separation, stream helper, deletion idempotency, diagnostic health.
