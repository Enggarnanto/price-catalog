# Validation 2.1.0

Actual WordPress core (from local installation, without loading its wp-config), isolated lpm_integration database on loopback MySQL 8.0.30, PHP 8.1.10 and Chrome. No production database or site modified.

- 34 new server/database checks: schema 5, all eight currency symbols, explicit ID/US number parsing, selected columns, preserved mixed text, validation, responsive rendering, draft preparation/no-op, stale-selection conflict, fault rollback, verified unpublish, preserved drafts/history, separate cache event IDs, republish and catalogs.
- 21 real-browser checks against the isolated WordPress REST handlers: currency sample and persistence, column-move metadata, mobile settings, selection counts, bulk draft/unpublish, actual publish preview and explicit confirmation, partial validation failures, exact published USD output, both mobile orientations, viewport containment, horizontal scrolling, frozen first header/body column and no script errors.
- 49 existing product checks, 63 concurrency/versioning/rollback checks, 16 independent persistence-verification checks, 19 simplified-workflow/deletion checks, 19 pricing-renderer checks and 11 CSV/JSON data checks passed.
- PHP and JavaScript syntax checks passed; desktop options and mobile screenshots visually reviewed. ZIP folder root and all source-file hashes verified.

232 checks passed. Bulk selection is limited to the visible filtered page (up to 20 items). Each selected item commits independently; the UI reports partial failures and keeps publish operation keys for uncertain-response retries. Existing drafts that need reconciliation must be handled in the editor.

Currency is a display format, not exchange conversion. Public changes require publication. Cache/CDN purge still requires the site's existing invalidation integration. Mobile orientation can create a second hidden table view, increasing markup size for that resource. Snapshot schema 5 requires plugin 2.1.0 or newer.
