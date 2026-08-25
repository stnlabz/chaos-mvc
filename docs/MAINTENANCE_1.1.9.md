<!-- [AI:GPT-5.6 Sol | 2026-08-25 UTC] -->
# Pre-1.1.9 Maintenance

This maintenance release hardens authentication, administration, uploads,
database helpers, generated discovery files, and module updates.

## Core Updater release gate

The v1.1.9 release gate includes a dedicated Core/system updater. Its
requirements are defined in [`CORE_UPDATER_1.1.9.md`](CORE_UPDATER_1.1.9.md).
The Core Updater is distinct from the independently versioned module updater;
neither updater's authority, state, package, backup, or installation model
defines the other.

The implementation provides authenticated Core release checking, complete
signed-package staging, verified backup and installation, ordered migrations,
mandatory pre- and post-commit verification, public maintenance mode, and
administrator and standalone command-line recovery. The fixed Core signing
public key is embedded at `app/core/core_update_public_key.pem`; the
corresponding private release-signing key must remain outside the repository
and web server.

Validated Core operations use `/.chaos-update/` for isolated staging, locks,
hash-chained journals, temporary backups, and the single retained rollback.
The installed ownership manifest is retained separately at
`/.chaos-core-manifest.json`. Neither path is owned by a Core package or the
module updater, and both must be denied direct web access.

The administration workflow is **Check for Core update**, **Validate and
stage package**, then **Install staged Core update**. Public requests receive
HTTP 503 only while live Core changes or recovery are active; administrators
at user level 9 remain available.

If the application cannot bootstrap, run the standalone recovery utility from
outside the web server:

```text
php app/tools/core-recover.php --root=/path/to/chaos-mvc --confirm
```

Release construction, signing, publishing, and the update-service API belong
only to the authoritative `chaos-mvc.org` installation. They are not
distributed to Chaos MVC installations on other domains. The private PEM is
never part of this repository, public Core ownership, or package contents.

## Existing installations

Back up the database and application files before upgrading. Run
`app/install/migrations/1.1.9.sql` exactly once. Fresh installations already
receive the updated schema from `app/install/schema.sql`.

The migration adds missing password-reset and traffic tables and enforces
unique usernames, account email addresses, module slugs, and post slugs. If an
index cannot be created, identify and resolve duplicates before retrying:

```sql
SELECT username, COUNT(*) FROM accounts GROUP BY username HAVING COUNT(*) > 1;
SELECT email_address, COUNT(*) FROM accounts GROUP BY email_address HAVING COUNT(*) > 1;
SELECT slug, COUNT(*) FROM modules GROUP BY slug HAVING COUNT(*) > 1;
SELECT slug, COUNT(*) FROM posts GROUP BY slug HAVING COUNT(*) > 1;
```

Set `APP_URL` to the canonical application origin in production, for example
`https://www.example.com`. Set `CHAOS_DEBUG=true` only for local debugging.

## Signed module updates

Module updates must now be signed. Pin the publisher's PEM public key in the
local module configuration:

```json
{
  "version": "1.0.0",
  "update_url": "https://updates.example.com/module.json",
  "signing_public_key": "-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----"
}
```

Update metadata must provide `version`, `download`, `sha256`, and a Base64
`signature`. The signed message is these three values joined by line feeds:

```text
version
download
sha256
```

The signature must use SHA-256 with the private key corresponding to the
locally pinned public key. The package must be no larger than 20 MB and may
contain PHP files only beneath `controllers/`, `models/`, or `views/`.

## Verification

Run the maintenance checks from the project root:

```text
php tests/run.php
```
<!-- [End AI:GPT-5.6 Sol] -->
