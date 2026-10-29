# Validation 2.0.10

- 32 PHP logic/lifecycle checks (isolated WordPress/database API doubles): previous visibility/widget checks plus dynamic tag generation, alias persistence/idempotency, startup registration, collision protection/fallback, index tag inventory, old-name rendering, visibility overrides, invalid IDs, archived resources, catalogs and empty-name fallback.
- 13 Chrome writer UI checks: dynamic name+ID display/copy/insertion in Posts/Pages Classic and block-editor API fixtures, catalog insertion, mobile layout and published-only requests.
- 15 Chrome admin/export regression checks, including dynamic shortcode display and description selection.
- 11 CSV/JSON data checks; PHP/JavaScript syntax checks.
- ZIP plugin root and every packaged source file verified by SHA-256.

71 checks passed. Tests use isolated API doubles and mocked REST responses rather than a live WordPress/database deployment. No production data modified.

Dynamic names are registered for active and archived resources; the renderer still resolves by ID and only exposes published content. An immutable, non-autoloaded option per former name preserves aliases after rename. Tags already owned by WordPress/another plugin are never replaced; those names use the existing fixed-tag format. The existing snapshot schema remains unchanged from 2.0.9.
