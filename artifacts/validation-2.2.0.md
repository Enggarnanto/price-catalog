# Validation 2.2.0

WordPress core with isolated lpm_integration database on loopback MySQL, PHP 8.1 and real Chrome. No production site or database changed.

- 5 new server preview checks: administrator access, exact unsaved server rendering, no draft/version writes, writer restriction and catalog carousel markup.
- 24 real-browser editor checks: four tabs, complete matrix/preview, fit to dashboard, row search, nominal-cell display, no public changes during preview, hidden-field retention across tabs/save, currency settings, featured column, footer persistence, Paste Sheet, Enter to add row, history, device controls, 390px layout, catalog controls, reorder/alt retention and no script errors.
- 10 additional browser safety checks: keyboard tabs, actual 390px mobile/minimum 960px desktop frame viewports, preview errors retaining inputs and prior view, stale-response rejection, failed-save recovery and explicit publish confirmation.
- 49 product checks, 63 versioning/concurrency/rollback checks, 16 independent persistence checks and 11 CSV/JSON checks passed.
- PHP/JavaScript syntax checks passed. Desktop/mobile table/catalog screenshots and preview crops were inspected. ZIP root and all source files verified against SHA-256 hashes.

178 checks passed. The new preview endpoint is administrator-only and nonce-protected; it renders unsaved content without saving or publishing. The iframe is sandboxed without same-origin access, disables link navigation, and accepts resize messages only from its own window with a per-render token. Older preview responses cannot replace newer input.

Public renderer, database schema and snapshot schema are unchanged from 2.1.0. An additional canonical shortcode in the verified save receipt keeps the header correct after renames. Preview needs the WordPress REST API and local public assets; validation/network failure leaves prior preview visible with a notice. Long previews scroll inside a 540px panel and retain all rows. Save status accurately distinguishes browser backup from a verified server draft.
