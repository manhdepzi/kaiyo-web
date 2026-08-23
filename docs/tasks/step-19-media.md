# Step 19 — Media Task Record

- Status: `DONE`
- Inputs: Steps 04–11, 13 and 15 `DONE`; D-007 storage policy approved

## Scope

Implement object-storage-neutral media assets, quarantine-first upload validation, explicit usage references, safe image variants, private document access and a malware-scanner port. Storage configuration remains environment-driven and no provider is embedded in domain code.

## Acceptance

- [x] Claimed MIME and extension never override detected content.
- [x] Executable, polyglot, oversize and disallowed content fails closed before publication.
- [x] Quarantine and final paths are generated; original filenames cannot choose storage paths.
- [x] Private assets are accessible only through authorized temporary URLs or controlled responses.
- [x] References prevent deletion while in use; orphan cleanup is explicit and idempotent.
- [x] Image variant, scanner failure, signed access and storage adapter tests pass.

## Evidence

- Migration `2026_08_23_000007` adds governed Media assets/variants, five MySQL CHECK constraints, Catalog reference FK/uniqueness and two scoped Media permissions.
- `MediaService` uses generated quarantine/final keys, `fileinfo` content detection, MIME/extension agreement, configured byte limits and a replaceable `MalwareScanner` port. Rejected scans retain metadata evidence and publish no object.
- GD generates bounded WebP `thumb`/`large` variants; storage is selected by configuration and no provider SDK appears in domain code.
- Five Media tests/20 assertions cover safe image promotion, variant generation, mismatch/polyglot rejection, scanner fail-closed behavior, permission-gated temporary private URLs, reference protection and idempotent orphan cleanup.
- Full SQLite suite passes with 67 tests/456 assertions. Isolated MySQL 8.4 critical suite passes with 16 tests/61 assertions; rollback removed both Media tables, FK and permissions before `kaiyo_test` was dropped.
- PHPStan level 8 and Pint pass. The live `kaiyo` database was not migrated or mutated.
