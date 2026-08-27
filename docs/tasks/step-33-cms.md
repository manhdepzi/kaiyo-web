# Step 33 — CMS Task Record

- Status: `IN PROGRESS — MYSQL/BROWSER GATES OPEN`
- Started: 2026-08-25
- Contract: approved separate Page roots/revisions from the Step 07 schema dictionary.

## Implemented

- [x] Page root and immutable published-revision schema with opaque public IDs and reserved slugs.
- [x] Separate `content.manage` and high-impact `content.publish` server authorization.
- [x] Draft creation, optimistic publication and idempotent publish retry.
- [x] Public published-only DTO query and sanitized Markdown SSR route.
- [x] Private Admin Page directory/create/publish delivery with cursor filtering, 2FA and permission-filtered navigation.
- [x] MySQL trigger guards for published revision update/delete.
- [x] Safe Page replacement revision and idempotent unpublish without taking the previous public revision offline while editing.
- [x] Page publication schedule with unique operation identity, singleton scheduler wiring, retry safety and stale-target fail-closed behavior.
- [x] Separate Article, FAQ, Banner and Email Template roots/revisions (no generic content god table).
- [x] Published-only Article SSR, ordered/query-bounded FAQ SSR and approved Home Banner projection.
- [x] Banner CTA scheme validation and Email Template variable/executable-syntax/header-injection controls.
- [x] Private Admin CMS workspace for Article, FAQ, Banner and Email Template creation/publication with distinct manage/publish authority.
- [x] Type-specific replacement revisions and idempotent unpublish for Article, FAQ, Banner and Email Template; the previous published revision stays live until replacement publication.
- [x] Article/FAQ/Banner publish/unpublish schedules with stable operation identity, stale-target failure, singleton runner integration and Admin controls.
- [x] Governed Page/Article/FAQ/Banner media references with dual Content/Media authority, current-draft and clean-public-asset guards, optimistic versioning, stable attach/detach retry identity and orphan-deletion protection.
- [x] Admin Page and CMS media attach/detach delivery uses opaque asset identities and exposes only governed references.

## Verification evidence

- CMS/Media focused: 20 tests / 194 assertions.
- Full regression: 163 passed / 1129 assertions; four isolated MySQL trigger tests remain intentionally skipped on SQLite.
- PHPStan level 8, Pint and Vite production build pass.

## Remaining

- MySQL execution evidence for the new CMS immutable-revision triggers and final browser/query gates.

No migration was executed against the live `kaiyo` database in this task.
