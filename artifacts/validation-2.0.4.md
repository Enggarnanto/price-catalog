# Validation 2.0.4

- 14 WordPress/PHP/MySQL checks: editor hooks, admin bar and plugin actions, author metadata, published-only search, draft privacy and unchanged author mutation permissions.
- 15 browser checks using real WordPress JS packages, the block editor store and author REST requests: panel registration, shortcode copy, Classic Text cursor insertion, actual core/shortcode block insertion, restricted blocks, search, mobile overflow, Escape dismissal and plain-permalink routes. Classic Visual insertion is implemented through TinyMCE but was not exercised in a mounted TinyMCE session.
- PHP and JavaScript syntax checks passed. WordPress legacy namespace fallback checked in a fixture; WordPress 6.2 itself was not installed for this release.
- ZIP root, author, version, CRC and all source bytes verified.

Logo remains unchanged pending the user's artwork. No production website was changed.
