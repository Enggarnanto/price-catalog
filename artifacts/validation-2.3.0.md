# Validation 2.3.0

- 43 updater integration checks on real WordPress/PHP and the isolated database, with release HTTP fixtures: disabled default, HTTPS/public URL rules, validated JSON, sanitized changelog, cache reuse, update/no-update entries, preservation of other plugins, details API, SHA-256/root/version/path checks, native WP_Upgrader download hook, tamper/incompatibility/downgrade rejection, transport/JSON failures, capabilities, disable cleanup, nonce-protected install URL and expired-feed state.
- 19 additional GitHub checks: repository normalization, stable-release API, named ZIP assets, digest/companion checksum, optional manifest matching and compatibility, draft/prerelease/source-archive rejection, cross-repository asset rejection and actual ZIP-header requirements.
- 18 Chrome checks using native PHP forms and WordPress nonces: initial state, GitHub source/release UI, source persistence, compatible/current/incompatible/error status, forced manual check, native update link, mobile fit, disabling, rejected bad nonce and no JS errors. Release server responses were mocked for these tests.
- PHP/JavaScript syntax checks passed. Desktop and mobile settings screenshots visually inspected. ZIP source entries verified by SHA-256; companion release manifest carries its ZIP checksum.
- 49 existing WordPress product/data/rendering checks also passed after updater integration.

80 updater checks passed. The native upgrader's download phase was exercised with a fixture ZIP; no actual plugin replacement was performed. Live multisite installation and installation on a production host were not tested. No production site or price database changed. The real GitHub repository API was read separately: repository public, latest stable release HTTP 404. No GitHub release was published.

The updater ships disabled with GitHub repository Enggarnanto/price-catalog prefilled. A stable GitHub release with an installable ZIP must be published for that source to work. An alternative HTTPS manifest source remains available. No auto-install preference is enabled. Data/snapshot/database schemas and the pricing mutation pipeline are unchanged from 2.2.0.
