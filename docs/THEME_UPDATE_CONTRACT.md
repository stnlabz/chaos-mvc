# Theme Updates and Filesystem Recovery

Status: 1.1.10 development. The current release remains 1.1.9.

## Ownership and administration

- Module updates remain under `user/modules/{module}` and the existing `/admin/update` endpoint.
- Theme updates operate only under `user/themes/{theme}`, through POST actions on `/admin/themes`.
- Core updating, routing, authentication, and Sentinel are unchanged.
- Theme selection still requires administrator level 7. Theme checking, installation, and rollback require level 9 and valid Core CSRF protection, matching module maintenance authority.
- The Themes administration page uses the shared ChAoS MVC header and footer and inherits the active theme. It does not load a separate Bootstrap layout. A broken active theme can therefore affect this page as well; repair its files or select the Core fallback through installation configuration before using the interface if necessary.
- Listing installed themes performs no network requests on the server. The page initiates separate asynchronous version checks for themes with an HTTPS update source.

## Installed theme.json

Keep the existing `theme` identity. Release and trust fields mirror the module contract:

```json
{
  "theme": "example",
  "name": "Example Theme",
  "version": "1.0.0",
  "creator": "Developer name",
  "description": "Example theme",
  "update_url": "https://developer.example/updates/example.json",
  "package_hosts": ["packages.developer.example"],
  "signing": {
    "algorithm": "rsa-sha256",
    "key_id": "developer-release-key",
    "public_key": "-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----"
  },
  "files": ["theme.json", "inc/head.php", "inc/nav.php", "inc/foot.php", "assets/style.css"]
}
```

This is a shape example, not a usable key. `author` remains supported for existing theme display metadata; `creator` takes precedence when present. `package_hosts` is optional when the package is served by the manifest host. The private signing key is never installed on consumer sites.

Existing local-only themes continue working. No update URL or signing trust is invented for Classic or other installed themes.

## Remote announcement JSON

```json
{
  "theme": "example",
  "version": "1.0.1",
  "download": "https://packages.developer.example/example-1.0.1.zip",
  "sha256": "<64 lowercase hexadecimal characters>",
  "key_id": "developer-release-key",
  "signature": "<base64 RSA-SHA256 signature>"
}
```

Version discovery compares the identity and version, like modules. An “Update available” result is not proof that the package is authenticated; full verification occurs on installation.

Sign these exact UTF-8 lines, joined with LF, without a trailing newline:

```text
CHAOS-MVC-THEME-RELEASE
theme=example
version=1.0.1
download=https://packages.developer.example/example-1.0.1.zip
sha256=<64 lowercase hexadecimal characters>
key_id=developer-release-key
```

Use RSA-3072 or stronger with SHA-256, or an OpenPGP detached signature, then base64-encode the signature bytes. The different prefix and `theme=` identity prevent module signatures from authorizing theme packages. Module signing statements remain unchanged. Both updaters accept base64 public keys and use the same [release signature contract](RELEASE_SIGNATURE_CONTRACT.md). PGP requires PHP GnuPG 1.5+; there is no checksum-only fallback.

## Package contract

The ZIP contains one top-level directory matching the theme slug:

```text
example/
  theme.json
  inc/head.php
  inc/nav.php
  inc/foot.php
  assets/style.css
```

Root-level `head.php`, `nav.php`, and `foot.php` are also accepted, matching the existing resolver. All three parts are required. Packaged identity and version must match the verified announcement. PHP is parsed before activation without executing it; parsing does not demonstrate runtime compatibility.

The module updater's limits apply: 1 MiB metadata download, 25 MiB package download, 2,000 archive entries, 10 MiB per file, and 50 MiB expanded total. Paths must be portable and confined to the named theme; traversal, symlinks, and case-colliding names are rejected. HTTPS uses port 443, verified TLS, validated/pinned public addresses, bounded responses, and no redirects. The package host must be the manifest host or an explicitly configured local package host.

Installed `update_url`, `package_hosts`, and `signing` remain installation-owned. A package cannot replace or introduce these trust settings. Theme updates do not execute module SQL migrations or modify the active-theme selection.

## Filesystem rollback

Successful updates retain one previous filesystem release:

- Modules: `user/modules/.{module}.previous`
- Themes: `user/themes/.{theme}.previous`

A subsequent successful update replaces the retained version. Manual rollback consumes it; it is not a toggle between releases. “Restore previous files” uses POST, CSRF, administrator authorization, and the same maintenance lock as updating. Restoring a broken module does not execute its controller.

Restoration replaces the whole module/theme directory. Local edits or runtime files stored inside that directory are also replaced by their snapshot versions; preserve any newer local data separately before manual rollback.

Caught activation/retention failures attempt automatic restoration and report whether restoration succeeded. Cleanup failures after completed activation do not falsely report an installation failure. If restoration itself fails, preserve the recovery directories and inspect them before retrying.

**Module filesystem rollback does not reverse SQL.** Administrators must confirm this and review compatibility before restoring old PHP. A failed DDL migration may be partially applied. A completed migration remains completed even if source activation fails. Existing migration-journal/retry behavior remains authoritative.

Nuke removes the normal retained module snapshot before database destruction. If snapshot cleanup fails, the quarantined live module is restored and SQL destruction is not started. Exceptional `.backup-*` or `.rollback-*` recovery directories left after failures require operator review; do not discard potential recovery files automatically.

Process termination, server crashes, subsequent runtime failures, and remote health checks are not automatically detected/recovered by this implementation. One retained filesystem version is not a database backup or a substitute for an independent site backup. Runtime snapshot/staging/lock directories are Git-ignored and must not be included in release packages.

## Live-domain acceptance checklist

Local fixture tests do not qualify a deployment. On an authorized test domain:

1. Verify a local-only theme still applies without any update source.
2. Configure a developer-signed theme and verify “Up to date” and “Update available”.
3. Update an active theme and verify its PHP, assets, version, and unchanged selection.
4. Restore the previous theme and verify its rendering/assets, including the host's OPcache behavior.
5. Confirm `/admin/themes` inherits the current theme's header, navigation, footer, and styling.
6. Confirm a rejected/tampered package leaves the live theme unchanged.
7. Update and roll back a module; separately inspect any SQL migration compatibility.
8. Nuke a disposable module and confirm its owned tables, live files, and normal retained snapshot are removed.
9. Confirm level-7 users cannot update/rollback and invalid-CSRF requests cannot mutate files.
10. Exercise failure recovery on a disposable installation; never introduce filesystem failures on a production site.
