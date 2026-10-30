## 2.4.1 - 2026-10-30

- Katalog memakai frame artwork dengan indikator titik dan deskripsi di kiri bawah, serta CTA di kanan.
- CTA per gambar menggantikan CTA katalog pada slide tersebut; CTA katalog menjadi fallback tanpa tombol ganda.
- Mode Single Image/Carousel dan pengaturan autoplay dipindahkan ke section 1 Gambar & Urutan.
- Navigasi titik, swipe, keyboard, reduced motion, dan galeri tanpa JavaScript tetap tersedia.

## 2.4.0 - 2026-10-29

- Add Import JSON for exported table/catalog collections and single local backups, with full validation, destination preview, per-item selection and results.
- Support creating new IDs or updating existing IDs as drafts; preserve metadata, descriptions, original visibility, prices, formatting, links and CTA without changing production/history.
- Add signed preview targets, revision/base/production conflict checks and atomic import receipts for idempotent retry, including concurrent and lost-response cases. Database schema 5 adds import_operations; snapshot schema stays unchanged.
- Warn about existing draft replacement and mismatched destination names. Clear mismatched/foreign media IDs and require Media Library reselection before catalog publication.
- Validate against the actual 18-program JSON and isolated WordPress/database/browser regressions. No production imports were performed.

## 2.3.0 - 2026-10-29

- Add a dedicated Updater settings page with disabled-by-default GitHub Releases source (Enggarnanto/price-catalog), optional HTTPS manifest source, manual check, installed/latest version, check timestamp, requirements and native WordPress update links.
- Integrate with plugin update transients, plugin information and the native upgrader download hook. Add Update URI to avoid unrelated WordPress.org slug updates.
- Validate public HTTPS URLs and redirect destinations, manifest schema, release requirements, SHA-256 ZIP checksum, plugin folder/name/version and archive paths before installation.
- Cache successful/error checks and remove stale offers on disabled, failed or expired sources. Preserve other plugin update entries and respect administrator/network/install permissions and nonces.
- Include GitHub release-asset selection, digest/checksum verification, optional per-release requirements manifest, publishing documentation and release companion files. No release is automatically published and no auto-install preference is enabled. Pricing data and database/snapshot schemas are unchanged.

## 2.2.0 - 2026-10-19

- Redesign table/catalog editing around four accessible tabs, top actions, compact editable matrix, row search, explicit Paste Sheet, featured-column shortcuts and a persistent save/action bar.
- Add catalog artwork cards with ordered controls and grouped metadata/CTA fields; preserve edits across tab and image-order changes.
- Replace approximate table-only samples with nonce-protected, administrator-only unsaved previews rendered by the actual public renderer for both tables and catalogs.
- Add sandboxed Desktop/Mobile preview frames that scale to dashboard width, resize to content and scroll inside a bounded panel; retain all data and support carousel controls.
- Preserve prior preview on validation/network failure, reject stale responses and maintain explicit publish review/confirmation.
- Return canonical shortcode with verified save receipts so renamed resources retain correct copy controls; fix dashboard selection-column widths.
- Validate with isolated live WordPress/database/browser checks and visual desktop/mobile review. No public snapshot or database schema changes.

## 2.1.0 - 2026-10-19

- Add per-page resource selection and bulk Prepare Draft, Unpublish and Publish for tables/catalogs, with preview before publishing, revision checks and per-item partial results.
- Add verified transactional draft preparation and withdrawal that preserve drafts/history and queue independent cache invalidation events. Database schema 4 separates event uniqueness by version and transition type.
- Add per-table currency selection (IDR/USD/EUR/GBP/SGD/MYR/AUD/JPY), 0/2 decimals, Indonesian/international number formats and selected nominal columns. Preserve source text, mixed content and links; no exchange conversion.
- Add independent mobile orientation and optional frozen first header/body column, contained scrolling and an accessible CSS switch between desktop/mobile views.
- Introduce snapshot schema 5 for table options; retain prior snapshot rendering and restoration. Keep nominal-column metadata attached during column moves/deletion.
- Validate with live isolated WordPress/database/browser checks, existing versioning/persistence/product regressions and visual mobile review.

## 2.0.10 - 2026-10-19

- Replace newly generated fixed-tag shortcodes with the package-name slug and stable ID, e.g. `[paket-reguler id="12"]`, for tables and catalogs.
- Register current/archived names and persist former names as immutable aliases during rename; retain all legacy shortcode formats and per-article visibility options.
- Resolve dynamic tags by resource ID through the published-only renderer and include dynamic tags/aliases in article usage scanning.
- Preserve existing WordPress/third-party shortcode handlers and fall back to the compatible fixed-tag format on name collisions.
- Verify dynamic lifecycle logic and browser copy/insertion/export flows using isolated API doubles and fixtures.

## 2.0.9 - 2026-10-19

- Add a visible shortcode widget to Posts and Pages, with Classic and block-editor insertion, plus support for users with page-only editing capability.
- Include package-name slug and stable resource ID in newly copied/inserted shortcodes; retain old ID-only shortcode rendering and usage indexing.
- Add independent description visibility to the resource editor (snapshot schema 4) and optional per-article title/description overrides in the shortcode picker.
- Display plugin version and creator in Pricelist navigation, shortcode widget and picker footer.
- Verify PHP logic, Posts/Pages insertion and visibility controls with isolated API doubles, browser fixtures and export regressions.

## 2.0.8 - 2026-10-19

- Add administrator CSV/JSON export for price tables and image catalogs, per item and across all pages matching the applied library filters.
- Add editor export of unsaved fields and a choice of latest draft/current content or published content without saving or publishing.
- Include catalog image order, URLs, alt/caption, price, hyperlinks and CTA; retain full resource content in JSON.
- Encode CSV with UTF-8 BOM, multiline quoting and spreadsheet formula escaping; show read failures without downloading partial results.
- Verify with 11 data checks and 12 real-browser checks using isolated REST fixtures; document export scope and exclusions.

## 2.0.7 ? 2026-10-17

- Add optional reference-style pricing cards: navy package headers, subtitles, badges, yellow featured package accent, colored education-level markers and separate Rp prefixes.
- Add configurable consultation footer, optional button and destination-based WhatsApp icon. Reuse existing CTA URL and new-tab settings; no phone number or placeholder link is inserted automatically.
- Add editor controls, matching live sample, responsive table scrolling and stacked mobile footer.
- Keep package metadata attached during column moves/removal and normalize dimensions after grid paste/CSV import.
- Introduce snapshot schema 3 for presentation fields. Existing schemas 1/2, horizontal transpose, catalog rendering and old published snapshots remain supported.
- Validate with server persistence/rendering checks and real-browser editor/mobile checks, plus prior product, versioning and persistence regressions.

## 2.0.6 ? 2026-10-17

- Fix the full-resolution logo overflowing the WordPress sidebar and obscuring Dashboard content.
- Use a 20x20 SVG image wrapper containing the original PNG, so intrinsic dimensions stay safe before plugin CSS loads. Header logo remains unchanged.
- Load narrowly scoped sidebar sizing CSS on every admin screen, before the Pricelist-page filter. Other pages do not load the Pricelist application styles.
- Reproduce the previous overflow using actual WordPress menu CSS, then verify desktop, hover, folded menu, mobile and CSS-unavailable behavior. Verify the Dashboard enqueue hook separately.

## 2.0.5 ? 2026-10-16

- Install the supplied original PNG logo in the Pricelist header and WordPress menu. Preserve the source image unchanged.
- Keep the PL / standard menu icon fallback when a logo asset is unavailable. Version bump refreshes cached plugin CSS and JavaScript.

## 2.0.4 ? 2026-10-16

- Add a published-only Pricelist picker for SEO writers: Gutenberg document settings panel, Classic Editor media-toolbar button, search, type filter, pagination, copy, and direct shortcode insertion.
- Add Pricelist and documentation shortcuts to the WordPress admin bar and plugin action links.
- Keep writer permissions unchanged; unpublished and deleted resources remain excluded.
- Update author to Galih Enggarnanto, author website to mtalkblog.com, and plugin/documentation URL to the supplied guide.
- Validate author permissions and metadata with 14 WordPress/MySQL checks, plus 15 browser checks with real WordPress JS packages, block store, and author REST requests.

## 2.0.3 ? 2026-10-16

- Simplify save/publish: optional change notes, automatic synchronization of nonconflicting edits, and no manual server reload.
- Preserve and automatically recover unsaved browser input; keep downloads as an optional backup only.
- Handle revision races with bounded retries. Real conflicts require a single explicit replacement choice, preserving the previous server draft in browser storage.
- Use unique GET URLs and verified server receipts for save/publish; remove the redundant HTTP readback that could turn a successful commit into a false failure.
- Keep optimistic revision locks, independent post-commit verification, immutable history, signed publication review and idempotent publication retries.
- Add confirmed, administrator-only soft deletion for tables/catalogs, revision checks, durable cache invalidation, and archived database history. Deleted items no longer appear in the library or render through shortcodes.
- Upgrade database schema to 3 with a nullable deleted_at column; snapshot schema remains unchanged. Fix mobile history overflow.
- Validate with actual WordPress 6.8.3 / PHP 8.1.10 / MySQL 8.0.30, failure injection, concurrent database workers, and headless Chrome against the real REST handlers.

## 2.0.2 ? 2026-10-16

- Fullwidth admin workspace, responsive navigation, filters, forms, and horizontally scrollable tables using the supplied blue/slate/green design direction.
- Local matrix updates preserve metadata and history without fetching or rebuilding the entire editor. New rows and columns receive focus after the request lock is released.
- Spreadsheet-style Tab / Shift+Tab and Enter navigation; Shift+Enter retains multiline entry.
- CSV apply now saves the draft directly while its dialog owns the request lock; removed a nested run call that silently skipped saving.
- Successful draft verification clears the local backup before rendering, avoiding a false recovery warning.
- Manual server refresh moved to secondary options. Existing persistence verification, conflict checks and publication review remain in place.
- Validation: 18 headless Chrome checks with mocked REST, including desktop/mobile overflow, grid/paste, stale readback, save and CSV persistence. Live WordPress integration was not rerun for this release.

# Changelog

## 2.0.1

- Save/publish menggunakan koneksi database khusus agar transaksi WordPress lain tidak membatalkan tulisan plugin.
- Verifikasi sesudah commit memakai koneksi terpisah; publikasi memeriksa snapshot, operation key, pointer production, nomor versi, dan cache event.
- Pembacaan editor/history/publikasi memakai jalur database yang sama dengan penyimpanan, tanpa hasil query cache wpdb.
- Kegagalan verifikasi mengembalikan HTTP 503 `lpm_persistence_unverified`, tanpa mengklaim sukses.
- Editor membaca seluruh kontrol form sebelum save, termasuk nilai yang berubah tanpa event input. Hasil save dibaca ulang sebelum cadangan lokal dihapus.
- Publish mempertahankan operation key sampai production hasil reload terverifikasi, sehingga retry tidak membuat versi ganda.
- Perbaikan penyimpanan karakter persen pada SQL native hasil wpdb::prepare.
- Asset version dinaikkan ke 2.0.1 agar browser memuat editor terbaru. ID tabel dan shortcode tetap.

## 2.0.0

- Editor grid: header, sel, baris/kolom, urutan, link per sel dan paste spreadsheet.
- Tabel vertikal/horizontal, program Reguler/Eksklusif, filter dan pencarian.
- Daftar resource yang menampilkan shortcode, usage, waktu update/publish dan status draft.
- Save dengan revision dan pembacaan balik database; cadangan input lokal serta retry publish.
- CSV dengan preview, tujuan resource baru/existing, dan validasi server.
- Katalog single image/carousel, galeri berurutan, link gambar serta CTA terpisah.
- Styling global dan override individual.
- Pustaka shortcode published untuk author dan indeks artikel/synced blocks.
- Snapshot schema v2, pembaca/adapter legacy v1 tanpa perubahan history atau ID shortcode.
- Transaksi atomik, history, restore, concurrency dan outbox tetap dipertahankan.

Pemeriksaan visual browser v2 belum terverifikasi; lihat VALIDATION.md pada proyek sumber untuk hasil dan batas tes yang sebenarnya.
