# Validation 2.4.0

- 50 live WordPress/database server checks: actual 18-program export, exact prices/settings/descriptions, UTF-8 BOM, single backup, malformed/oversized/duplicate/unsafe data rejection, read-only preview, atomic creation, same-operation retries, preview signing, draft-only existing updates, immutable production/history, metadata, stale conflicts, missing/type-mismatched targets, rollback/lost response, catalog media warnings, author denial and two independent concurrent import connections producing one ID.
- 17 Chrome/real-REST UI checks: direct menu, 18-row preview, program descriptions, selected-only import, deliberately dropped post-commit response, exact-ID retry, disabled completed items, mobile containment, preview invalidation, same-ID updates, unchanged published HTML, malformed file, backup, catalog warning and verified draft, no script errors.
- 49 existing product, 63 version/concurrency/rollback, 16 persistence, 19 simplified workflow/deletion, 19 pricing renderer and 11 CSV/JSON export checks passed.
- 43 updater and 19 GitHub-release checks passed with future-version fixtures updated for 2.4.0.
- PHP/JavaScript syntax checks passed; desktop/mobile import screenshots inspected. Release ZIP and companion manifest verified against the current source/checksum.

306 checks passed. Tests used isolated lpm_integration on loopback MySQL/PHP/WordPress and a real Chrome instance. The actual program JSON was read for validation; its contents and the production site/database were not modified.

All imports are drafts. Each resource commits independently; concurrent/stale updates are not silently overwritten. Schema 5 adds import receipts; public snapshot schema and rendering remain unchanged. Media binaries, global options and old version history are not restored by a JSON file. Client retry identity persists within the browser session; deliberately starting a fresh batch can create additional copies.
