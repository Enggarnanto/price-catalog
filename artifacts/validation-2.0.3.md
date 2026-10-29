# Validation 2.0.3

WordPress 6.8.3, PHP 8.1.10, MySQL 8.0.30 and headless Chrome, isolated loopback test database. No production website was modified.

- 63 server regression checks: revision locks, concurrency, rollback, immutable history and cache retries.
- 49 product checks: matrix, catalog, CSV, usage, styling and permissions.
- 16 persistence fault-injection checks: independent committed-state verification and lost-response retries.
- 19 workflow/deletion checks: verified receipts, soft deletion, authorization, stale revision rejection, rollback and archive retention.
- 31 browser checks using actual REST handlers and database: editing, concurrent edits, 409 retry, conflict choices, response loss, old browser backups, CSV, publish, automatic reconciliation, mobile overflow and confirmed deletion.
- PHP syntax lint and JavaScript syntax check passed.
- Installable archives contain only the price-catalogs folder; every entry matches source bytes.

178 checks passed. Server-specific hosting/proxy behavior on tutorindonesia.co.id was not directly tested.
