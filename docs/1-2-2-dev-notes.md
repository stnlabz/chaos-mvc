# v1.2.2 Development Notes

## Manual Rollback Removed — 20261101

Core no longer exposes manual rollback of an installed module or theme.
The Admin rollback buttons, POST rollback actions, and retained
`.previous` release snapshots were removed. An update still keeps an
isolated temporary backup while activating the new version and automatically
restores the old files on a failed activation. After a successful activation,
temporary backup directories are deleted. Historical `.previous` snapshots
are cleaned during a later successful update, not used for rollback.

This is a source change pending live qualification. It intentionally does not
promise reversal of completed database migrations.


## Versioned Module ZIP Roots — 20261101

Module updates accept either `lifecycle_test/` or a matching versioned
root such as `lifecycle_test-1.0.1/` within the ZIP. Archive validation
enforces exactly one matching module root; it still rejects unrelated root
entries, traversal paths, symlinks, duplicate paths, or missing module
controller/metadata. The validated archive root is used consistently during
staging, metadata and version checks, migration resolution, and file copying.
The module is always installed under its unversioned module slug.

This addresses DCT-packaged versioned ZIPs without rebuilding the package
or changing its published SHA-256 checksum.


## Module and Theme Update Simplification — 20261101

Module and theme update installation no longer requires RSA/PEM public keys,
private-key signatures, or key IDs. A configured HTTPS update URL advertises
a newer version and package location. The downloaded ZIP must match the
manifest SHA-256 checksum before its contents are extracted or installed.

Both remote manifest formats are accepted:

- `"sha256": "<64-character hex SHA-256>"` at the manifest root
- `"signing": {"algorithm":"sha256","sha256":"<64-character hex SHA-256>"}`

If both are present, the checksums must agree. The nested `signing` field
is a checksum declaration, not publisher authentication. Legacy RSA signing
fields are no longer required and are removed from locally installed module
and theme metadata as part of a successful update.

HTTPS source restrictions, authorized package hosts, ZIP path confinement,
archive validation, temporary staging, integrity verification, and atomic
replacement/automatic failure recovery remain in place. Checksum verification detects package
mismatch, but cannot establish publisher identity if both manifest and ZIP
are compromised.

Various things to be addressed, added, removed or updated.

## Core Updater Serialization — Implemented 20261102

Core update and rollback now share a non-blocking exclusive operation lock.
Concurrent authorized maintenance requests cannot operate on the shared Core
package, staging, backup, maintenance, or status lifecycle. Verification before
activation, failure recovery, and the single previous-Core rollback remain
unchanged.

## Core Diagnostic Service — Implemented 20261031

CDM is now a protected, level-9 Core component available at `/admin/cdm`.
It provides passive runtime observations, controlled capability probes, and
bounded inspection of installed user modules without executing module PHP.
Server identity, ownership, permission bits, and configuration values remain
observations until an operation through the active web runtime demonstrates
the corresponding capability.

See `CDM.md` for the diagnostic contract and operating boundaries.

## Installer Preflight Scope — Corrected 20261101

An Apache2 test (`Apache/2.4.66`, PHP `8.5.4`, `apache2handler`,
runtime user `www-data`) demonstrated successful required capability checks
except for `tmp/updater/` and `backups/updater/`, which are not part of a
fresh installation. Their absence unnecessarily blocked installation.

Removed these two Core Updater workspaces from installer qualification and
`prepareRuntimeDirectories()`. The user confirmed that this correction
resolved the reported preflight refusal.

Core Updater workspace and rollback path readiness must be checked when those
operations are performed. The installer continues to qualify its existing
mandatory runtime resources, including `app/data/updater/`. This test does
not establish complete Apache2 or Hostinger deployment qualification.

See `PREFLIGHT.md` for the installation-versus-update capability boundary.

## PHP 8.5 Compatibility — cURL lifecycle
- Remove the deprecated `curl_close()` call from `app/controllers/site_json.php`. Review confirmed no other ChAoS MVC Core use of `curl_close()`.

## Web Server Compatibility
See PREFLIGHT.md

## ChAoS MVC Logging

Core Logging Reliability — Confirmed Pre-Bootstrap Coverage Gap

Major errors do not consistently reach the ChAoS log configured in `app/bootstrap.php`.

### Verified Server Evidence — 20261024

Inspection of the Apache 2 deployment at `/var/www/stn-chain.org` established:

- The PHP runtime account `www-data` can traverse and write the installation-owned `logs/` directory.
- `logs/site_errors` is owned by `www-data` and is writable by that account.
- PHP accepts `/var/www/stn-chain.org/logs/site_errors` as its `error_log` destination.
- A direct `error_log()` probe successfully reached `site_errors`.
- Existing ChAoS exception records and stack traces are present in `site_errors`.

Filesystem capability and the ChAoS `error_log` destination are therefore not the general cause of missing records.

The current `public/index.php` performs `session_set_cookie_params()` and `session_start()` before loading `app/bootstrap.php`. The bootstrap configures `error_log`, enables PHP logging, and registers the ChAoS error and shutdown handlers. Errors during session initialization, errors in `public/index.php` before the bootstrap include, PHP startup failures, and parse or compilation failures that prevent the bootstrap from executing cannot reach the ChAoS handler or its configured log.

The inspected Apache service was disabled and inactive. Its enabled SSL virtual host also failed `apache2ctl configtest` because the configured Let's Encrypt certificate file did not exist or was empty. That Apache startup failure occurs before PHP executes and must be reported through the web-server/service logging boundary rather than the ChAoS application log.

### Required Follow-Up

- Establish a minimal ChAoS logging/error initialization boundary before session setup without moving unrelated bootstrap behavior or changing Sentinel execution order.
- Preserve the normal full bootstrap as the authority for framework initialization.
- Verify handled exceptions, session-start failures, required-file failures, and shutdown-detected fatal errors under an actual web SAPI.
- Keep web-server startup, PHP startup, and failures that occur before application execution within the server/SAPI logging boundary.
- Confirm the production deployment defaults `APP_DEBUG` to `false`; the inspected deployment defaulted it to `true` when the environment variable was absent.
- Retest using the actual runtime account, SAPI, virtual host, and configured log destinations after Apache configuration is valid and the service is running.

No Core correction has yet been implemented. The verified failure boundary now authorizes focused design and testing; it does not justify moving the entire bootstrap ahead of session initialization.

## Concerns

- What relevant security and compatibility intelligence has emerged since the previous review, and does any of it apply to ChAoS MVC?
  - MariaDB Security Announcements
  - PHP
  - cURL
  - Web Servers
    - LiteSpeed / OpenLiteSpeed
    - Apache
    - IIS
    - Other relevant platforms
  - OpenSSL
  - CMS Security Concerns
    - WordPress
    - Joomla
    - Drupal

### Review Rule

External security or compatibility intelligence does not establish a ChAoS MVC issue by itself.

For each concern:

1. Identify the reported vulnerability, compatibility issue, or behavioral change.
2. Identify the underlying mechanism or affected technology.
3. Determine whether ChAoS MVC uses or exposes an equivalent mechanism.
4. Determine whether the concern is applicable to ChAoS MVC.
5. Record the evidence supporting the determination.
6. Do not modify ChAoS MVC solely because another project or platform is affected.

A concern may be classified as:

- **Applicable** — evidence establishes that ChAoS MVC is affected.
- **Potentially Applicable** — a relevant mechanism exists, but impact has not been established.
- **Not Applicable** — the affected mechanism does not apply to ChAoS MVC.
- **Unknown** — available evidence is insufficient to make a determination.

A finding does not authorize a patch.

Patch authorization remains governed by the established ChAoS MVC Patch Requirements.

### Fixed - 20261007

- Fixed media deletion failing when retrieving a media record by ID.
- Updated `media_model::get_by_id()` to use a prepared PDO statement with parameter binding instead of incorrectly passing parameters to `PDO::query()`.
- Resolves the `PDO::query(): Argument #2 ($fetchMode) must be of type ?int, array given` TypeError encountered when deleting media images.

### Implemented - 20261022

- Added `developer` and `ai` account classifications. Both use authority level 7 and receive no module administration authority unless a level-9 administrator grants it explicitly.
- Added Core-owned per-account module Admin grants and enforcement at Admin delegation and direct module Admin routes.
- Added account-management controls for assigning classifications and module access.
- Kept protected Core administration and maintenance operations level-9 only.
- Added `docs/ADMIN_ACCESS.md` as the authority and enforcement reference.
