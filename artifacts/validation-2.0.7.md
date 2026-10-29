# Validation 2.0.7

WordPress 6.8.3 / PHP 8.1.10 / MySQL 8.0.30 / headless Chrome on isolated loopback fixtures.

- 49 prior product checks.
- 63 versioning/concurrency/rollback regression checks.
- 16 persistence fault-injection checks.
- 31 real-browser save/recovery/publish/deletion regression checks.
- 19 new pricing schema, validation, renderer, immutable history and exact restore checks.
- 16 public pricing/editor browser checks: reference layout, featured column, CTA, mobile scrolling, metadata-preserving moves/add/remove, save and live theme changes.

194 checks passed. PHP/JavaScript syntax checks passed. ZIP root, version, CRC and every source file verified; sample phone number exists only in excluded test fixtures.

Existing v1/v2 snapshots retain their old layout. New optional presentation is schema 3 and must not be downgraded to plugins before 2.0.7. Package layout uses the reference palette and vertical orientation; horizontal tables retain the standard renderer.

No production site or existing live price data was modified.
