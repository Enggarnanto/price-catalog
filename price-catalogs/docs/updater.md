# Updater plugin (2.3.0)

Open **Pricelist → Updater**. GitHub Releases is the default source and `Enggarnanto/price-catalog` is prefilled. Checking remains disabled until enabled by an administrator. Existing manual ZIP upgrades remain supported.

## GitHub Releases (recommended)

1. Create a stable release in [Enggarnanto/price-catalog](https://github.com/Enggarnanto/price-catalog/releases) with tag `v2.3.0` (future versions use their matching tag).
2. Upload `latis-price-catalogs-2.3.0.zip` as an actual release asset. `price-catalogs.zip` is also supported. GitHub's automatic **Source code** archives are deliberately ignored because their root is not the installable plugin folder.
3. Upload the companion `price-catalogs-update.json` and `.zip.sha256` files from `dist/`. Their download URL and hash are already set for this repository/version. For future builds generate the new hashes and version numbers.
4. In WordPress, select GitHub Releases, keep `Enggarnanto/price-catalog` (a full GitHub repository URL ending `.git` also works), enable checks, save and click **Cek update sekarang**.

Only stable public releases are supported. Private repositories/tokens, draft releases and prereleases are not supported. An empty repository or one without a published stable release reports an error rather than claiming to be up to date. On 2026-10-29 the repository was confirmed public, while its latest-stable-release API returned HTTP 404; no release was created by this implementation.

The updater reads GitHub's latest-release API, selects a named plugin ZIP asset and verifies its SHA-256. It uses the GitHub asset `digest` when available; otherwise the exact ZIP filename plus `.sha256` companion is accepted. An optional `price-catalogs-update.json` asset supplies precise WordPress/PHP requirements and must match the release tag, ZIP URL and digest. Without this manifest the displayed baseline is WP 6.2/PHP 8.1; the actual ZIP header requirements are still checked before installation.

GitHub API errors/rate limits appear as check errors. Cached successful checks reduce API traffic. Do not replace bytes of an already published ZIP under the same asset/version.

## Alternative HTTPS JSON source

1. Host the installable release ZIP and a JSON manifest on a public HTTPS server you control.
2. Use the exact ZIP SHA-256 in the manifest. ZIP root must be `price-catalogs/`, main file `price-catalogs.php`, plugin name `Latis Price Catalogs`, and its header version must match the manifest.
3. Set the manifest URL, enable checking, save, then click **Cek update sekarang**.
4. A higher compatible version appears on the Updater screen and in WordPress's plugin-update list. The Update button opens WordPress's nonce-protected native upgrader; it does not install on a plain settings-page visit.

See [update-manifest.example.json](update-manifest.example.json). Replace its placeholder checksum before publishing. The companion manifest in `dist/` has the actual ZIP checksum and expected GitHub release download URL. For a different host, replace that URL. ZIP checksum verification is not a publisher signature: control of the configured HTTPS source is trusted to publish code.

Required keys: `slug` = `price-catalogs`, `version`, `download_url`, `requires` (WordPress minimum), `requires_php`, and a 64-character hexadecimal `sha256`. Optional `changelog` is sanitized HTML. Manifest size is limited to 128 KiB. Version must be higher than the installed plugin to offer an upgrade; downgrades are not advertised.

Example release workflow on Windows:

```powershell
(Get-FileHash -LiteralPath '.\dist\latis-price-catalogs-2.3.0.zip' -Algorithm SHA256).Hash.ToLower()
```

Upload the finished ZIP first, then publish the manifest referencing its HTTPS URL and checksum. Keep published ZIP URLs immutable. Do not hash an extracted folder or modify the ZIP after computing its checksum.

## Checks and installation

- WordPress's existing plugin-update checks query the configured source; no separate cron job is added. Valid feed results are cached for 6 hours; failed checks for 10 minutes. **Cek update sekarang** bypasses that cache.
- There is no automatic-install setting forced by this plugin. WordPress's Plugins screen controls optional auto-updates. Global filesystem/update restrictions remain in force.
- ZIP downloads are bounded at 50 MiB, verify SHA-256, reject path traversal/symlinks/wrong roots, and require the expected main-file version/name. ZIP contents may expand to at most 100 MiB/10,000 entries. PHP ZipArchive is required for the verified updater.
- Only public HTTPS endpoints are accepted. Credentials, custom ports, private/reserved IPs and HTTPS-to-HTTP redirects are rejected. At most three redirects are followed, validating every destination. TLS verification stays enabled.
- Older, failed or expired feed results never offer an installable stale package. Incompatible PHP/WordPress versions show the requirements and remove the package/install action.
- Administrators can configure the updater; installation also requires `update_plugins` and WordPress filesystem permission. On multisite the source uses network options and the menu is in **Network Admin → Plugins → Updater** for network administrators. Multisite behavior is implemented but was not exercised on a live multisite fixture.
- Update replaces plugin files through WordPress. Existing price resources, drafts, published snapshots and version history are not deleted by updater code. Use normal site backups when installing code updates.

## Troubleshooting

If the screen says **Belum diperiksa**, run a check. If a check fails, review the HTTPS source, JSON, checksum and hosting TLS. If the download URL expires or a package changed, publish a stable ZIP URL and check again. File-modification restrictions, filesystem credentials or disabled network access may need site-administrator configuration.

The plugin's `Update URI` separates it from unrelated WordPress.org releases. The installer hooks are documented by WordPress: [plugin update checks](https://developer.wordpress.org/reference/functions/wp_update_plugins/), [plugin information](https://developer.wordpress.org/reference/functions/plugins_api/), and [pre-download validation](https://developer.wordpress.org/reference/hooks/upgrader_pre_download/). GitHub documents [latest releases](https://docs.github.com/en/rest/releases/releases) and [release asset digests/download URLs](https://docs.github.com/en/rest/releases/assets).
