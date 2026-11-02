# Theme Update Contract

## Ownership and authority

Theme updates operate only within `user/themes/{theme}` through authenticated,
CSRF-verified POST actions in `/admin/themes`. Update operations require
level-9 authority. Theme selection retains its existing administration
authority.

The installed `theme.json` may pin an HTTPS `update_url` and optional
`package_hosts`. Core does not invent an update source for local-only themes.

## Remote manifest

The manifest must identify the theme, advertise a newer version, provide an
HTTPS package URL, and declare the package SHA-256:

```json
{
  "theme": "example",
  "version": "1.0.1",
  "download": "https://packages.example.org/example-1.0.1.zip",
  "sha256": "<64-character hexadecimal SHA-256>"
}
```

The equivalent nested checksum form is also accepted:

```json
{
  "signing": {
    "algorithm": "sha256",
    "sha256": "<64-character hexadecimal SHA-256>"
  }
}
```

If top-level and nested SHA-256 values are both present, they must match. The
legacy field name `signing` describes checksum metadata only. It is not a
digital signature and does not authenticate the publisher.

## Package and activation

The ZIP must contain one confined theme root with `theme.json`, the required
head, navigation, and footer parts, and only portable paths. Core rejects path
traversal, links, case collisions, oversized packages, mismatched identity or
version, unauthorized package hosts, and database lifecycle declarations.

Core verifies the downloaded ZIP checksum before extraction. PHP files are
parsed without execution before activation. The complete staged theme replaces
the installed directory as one owned unit. A temporary transaction backup is
used only to restore the original theme if activation fails.

Successful updates do not retain a user-triggered rollback copy. Legacy
`.previous` directories are removed by a subsequent successful update. Core
does not alter the active-theme selection.

## Acceptance boundary

Local tests do not qualify a deployment. Live acceptance must verify update
discovery, checksum rejection, active-theme rendering, failed-activation
recovery, authorization, CSRF enforcement, and host-specific OPcache behavior.
