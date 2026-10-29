# Validation 2.0.8

- 11 export data checks: CSV UTF-8 BOM/CRLF, quoting and multiline text, spreadsheet formula escaping, Unicode, table headers/positions, complete JSON, catalog image/CTA links, empty catalogs, bulk JSON and invalid input.
- 12 Chrome browser checks with real admin scripts and isolated mocked REST responses: per-item and bulk buttons, pagination, applied search, published/draft isolation, unsaved editor capture, editor state preservation, catalog CSV/JSON downloads, mobile dialog, read failure, no mutation requests and no JavaScript errors.
- PHP and JavaScript syntax checks passed. Mobile export dialog visually inspected.
- Installable ZIP built with plugin folder root and verified against every source file.

No production data modified. This release changes admin-side export only; browser checks use REST fixtures rather than a running WordPress/database. Prior database regression suites were not rerun.

CSV contains text and image URLs. JSON contains the full selected resource content, but excludes version history, inherited global styling option and media binaries. Export reads resources sequentially; it is not an atomic database backup across all resources. JSON import is not provided.
