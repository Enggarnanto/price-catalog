# Validation 2.0.9

- 19 isolated PHP logic checks: snapshot schema 2/3 compatibility, schema 4 validation, independent visibility and per-shortcode overrides, immutable content, old ID-only shortcodes, type isolation, package-name shortcode generation, Posts/Pages widget registration, page-only editor capabilities, nonce protection and creator/version output.
- 13 Chrome writer UI checks using real writer.js and mocked REST/editor APIs: name+ID shortcode display, copy options, version/author, Classic insertion at cursor and block insertion for both Posts and Pages, catalogs, mobile layout and published-only requests. Mobile screenshot visually inspected.
- 15 Chrome admin/export regression checks with isolated REST fixtures, including new description default/selection and named editor shortcode.
- 11 CSV/JSON data checks retained from 2.0.8.
- PHP and JavaScript syntax checks passed. Installable ZIP root and every source file verified by SHA-256.

58 checks passed. These tests use doubles/fixtures; a live WordPress/database regression suite was not run in this session. No production data was modified. Gutenberg insertion tests use a block-editor API double rather than a full Gutenberg installation.

Schema 4 snapshots require plugin 2.0.9 or newer. Existing stored snapshots are preserved; shortcode visibility overrides apply to a rendered copy only. Old ID-only shortcodes remain supported.
