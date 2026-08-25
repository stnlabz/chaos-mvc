<!-- [AI:GPT-5.6 Sol | 2026-08-25 UTC] -->
# Core Updater Requirements for v1.1.9

## Status

The Core/system updater is a v1.1.9 release-gate requirement.

This document applies the accepted operational and user-experience lessons
from the WordPress Core update reference study to Chaos MVC. Chaos MVC remains
authoritative. WordPress behavior is not a source of additional product scope
or an implementation architecture.

## Scope and identity

The Core Updater updates the Chaos MVC system release. It is not the existing
independently versioned module updater.

The two updater engines must remain separate in all of the following areas:

- authority and trust roots;
- update discovery and offered-release state;
- version state;
- metadata and package contracts;
- package validation;
- filesystem scope;
- backup and recovery scope;
- installation procedure;
- locking;
- progress and result state;
- administrative permissions and actions;
- error codes and audit information.

The module updater must not be converted, generalized, or reused as the Core
Updater merely because both features download and install software. Shared
low-level utilities are permitted only when they are authority-neutral and do
not merge either updater's policy or state.

## Authoritative Core state

The Core Updater must use the centralized Chaos MVC system version as its
installed-version authority. Module versions and module configuration files
must not determine, advance, or repair the Core version.

An offered Core release must remain distinct from the installed Core release.
Discovering or validating an offer must not change the installed version.

The installed Core version may be advanced only after the Core installation
and its required post-install verification complete successfully. A failed or
restored update must not report the offered version as installed.

## Required lifecycle

The Core Updater must implement an explicit, server-authoritative lifecycle:

1. Check for a Core update.
2. Validate the authenticity and structure of the offered Core release.
3. Compare the offered version with the installed system version.
4. Download the Core package into staging.
5. Verify the complete staged package.
6. Run Core-specific compatibility, permission, and storage preflight checks.
7. Acquire the Core update lock and repeat volatile preflight checks.
8. Create the Core backup required by the authoritative recovery model.
9. Enter the Core installation state.
10. Install the Core package according to the authoritative Core file and
    migration model.
11. Run Core-specific post-install verification.
12. Commit the installed Core version only after verification succeeds.
13. Remove or retain recovery material according to the Core recovery policy.
14. Release the Core update lock.
15. Return a precise final outcome.

Discovery, validation, download, installation, restoration, and completion
must be represented as distinct states. A completed HTTP request is not proof
of a successful Core update.

## Update discovery

The administrative interface must separate checking from installation.

Before a valid offer is known, the primary action is **Check for Core update**.
The interface must not present a generic installation action merely because an
update endpoint is configured.

A successful check must produce one of these states:

- no update available;
- valid Core update available;
- offered release is not installable, with the blocking reason;
- check failed, with the installed system unchanged.

When an update is available, the interface must identify the installed and
offered Core versions. The installation action must name the target version,
for example **Update Chaos MVC to 1.1.9**.

Checking must be read-only with respect to the installed Core system.

Production installations must check an official HTTPS service operated by
`chaos-mvc.org`. The Core endpoint must not be set by module configuration or
entered through the administration interface. TLS certificate validation is
mandatory and there is no HTTP fallback. A server-side override may exist only
for development and controlled testing.

The service returns one stable Core release applicable to the installed
version, not a release catalog. The installation must independently reject an
offered version that is malformed, equal to, or older than the installed
version. Downgrades, nightly builds, beta releases, and alternate channels are
outside this contract.

## Authority and authenticity

The Core Updater must accept release authority only from the Core update trust
model approved for Chaos MVC. Module publisher keys, module certification
state, module `update_url` values, and module metadata must never authorize a
Core update.

The Core release metadata and package must be authenticated and integrity
checked before the first live-system write. Authentication failure must stop
the update with the installed Core system unchanged.

Chaos MVC ships with one fixed embedded Core release public key. The matching
private key signs Core release metadata published through `chaos-mvc.org`.
There is no remote key replacement, automatic key rotation, or
administrator-configurable Core key. If the private key is compromised, trust
recovery requires manual installation of a trusted Chaos MVC release carrying
a replacement public key.

The signed metadata must bind the offered version, package URL, package
SHA-256 digest, package size, release time, minimum supported updater version,
and signed Core manifest digest. Release notes may be displayed but cannot
authorize installation. Missing required fields reject the offer; unknown
fields do not gain authority merely by being present.

## Package and staging

The Core package contract must be defined independently of the module package
contract. Module package restrictions and destination mappings do not define a
valid Core package.

The Core Updater must:

- download into a non-live staging location;
- enforce the approved Core metadata and package size limits;
- reject unsafe archive paths and unsupported entry types;
- verify package authenticity and integrity;
- verify the expected Core distribution shape;
- resolve every package entry to an approved Core destination;
- finish all package validation before modifying live files.

Each release is a complete Core package, not an incremental patch. Every file
owned by the new signed Core manifest replaces the installed file at that
path. Local modifications to Core-owned files are unsupported and are
intentionally overwritten.

The package must have one predictable release root and a signed manifest that
enumerates every Core-owned file and digest. Unexpected package entries,
unsupported types, and destinations absent from the manifest reject the
package. The Core package size limit is independent of the module package
limit.

The v1.1.9 Core ZIP contract is:

```text
chaos-mvc-{version}/
├── core-manifest.json
├── app/
├── docs/
├── tests/
└── other Core-owned root files
```

The archive must contain exactly one `core-manifest.json`. Its exact bytes must
match the `manifest_sha256` value authenticated by the release metadata. The
manifest uses schema version `1`, repeats the offered Core version, and lists
each Core-owned file with its installation-relative path, SHA-256 digest, and
uncompressed byte size:

```json
{
  "schema": 1,
  "version": "1.1.9",
  "files": [
    {
      "path": "app/bootstrap.php",
      "sha256": "...",
      "size": 1234
    }
  ]
}
```

`app/bootstrap.php` and `app/core/version.php` are mandatory manifest files.
Every non-directory archive entry other than the manifest must appear exactly
once in `files`, and every manifest file must appear exactly once in the
archive. File sizes and digests must match before staging succeeds. Symlinks,
absolute paths, traversal paths, drive-qualified paths, backslash paths,
duplicate entries, and unlisted files are rejected.

The entire `/public` tree is outside Core ownership and must not be read,
written, deleted, backed up, or restored by the Core Updater.
`app/core/config.php` is protected installation configuration: the Core
package must not contain or claim it, and package validation must reject an
attempt to do so. Files not owned by the previous signed Core manifest are not
deleted. This preserves independently versioned modules and other non-Core
installation state even when those files share parent directories with Core.

## Preflight

Before installation begins, the Core Updater must verify the conditions that
the approved Core package and installation model require. At minimum, the
preflight must cover:

- compatibility requirements declared by the authoritative Core release;
- writability of every approved live destination;
- space required for staging, the Core backup, installation, and safe cleanup;
- availability of required archive, cryptographic, database, and filesystem
  capabilities;
- absence of another active Core update.

The updater must report the specific failed preflight condition. It must not
invent a WordPress compatibility matrix or block on capabilities that Chaos
MVC does not require.

## Locking and concurrency

Only one Core update operation may install, restore, or finalize at a time.

The Core update lock must be distinct from all module update locks. A module
update lock must not imply that the Core system is locked, and a Core lock must
not be stored as module state.

The lock must cover the live-changing portion of the lifecycle and remain held
through restoration and finalization. The administrator must receive a clear
**Core update already in progress** response when another operation owns the
lock.

Core lock and recovery state is stored in the protected installation-local
`/.chaos-update/` filesystem directory. It must be outside `/public`, excluded
from Core packages, and inaccessible through the web server.

Lock acquisition must use an atomic filesystem operation. Lock state records a
random operation identifier, installed version, target version, start time,
and current phase. Only the owning operation may advance or release the lock.
A failed operation requiring recovery retains its lock and recovery state.
Stale-lock handling must inspect recovery state before clearing the lock and
must never assume that elapsed time proves the live Core is coherent.

## Backup and recovery

The Core backup must be defined by the Core installation and recovery model.
The module updater's per-module files and configuration are not a valid Core
backup unit.

Before the first live-system change, the updater must create and verify the
recovery material required to return the Core system to its previous coherent
state. Installation must not proceed if the required backup cannot be created
or verified.

If installation or post-install verification fails after live changes begin,
the updater must attempt the approved Core restoration procedure and verify
the restored system.

The final result must distinguish:

- update failed before live changes; installed Core unchanged;
- update failed after live changes; previous Core restored and verified;
- update failed; restoration incomplete or unverified; manual recovery
  required.

The backup contains every file owned by the installed Core manifest, the
installed manifest and version record, a verification copy of
`app/core/config.php`, and the operation journal. The configuration copy is for
verification and must not be installed as package content.

The successfully installed signed manifest is retained at
`/.chaos-core-manifest.json`. It is Core ownership metadata written by the
updater after final verification, not a package-owned file. The next update
uses it to determine the exact previous Core backup and obsolete-file boundary.
The manifest must remain schema-valid and must include path, digest, and size
for every owned file. Backup preserves the actual installed bytes and records
their backup digests; local edits to Core remain unsupported and are replaced
by the next complete release.

Each operation has an append-only, hash-chained journal at
`/.chaos-update/operations/{operation-id}/journal.jsonl`. A malformed or
hash-invalid journal cannot accept new events and blocks promotion or recovery
until it is inspected.

The updater retains exactly one completed previous-Core backup for rollback.
After a later update succeeds, its pre-update backup replaces the older one.
Temporary material for an unresolved failure may remain until recovery is
complete, but it must not become a second retained rollback version. Recovery
never replaces `/public`, module state, or unrelated installation data.

Temporary backups remain beneath the owning operation until the new Core is
verified. Only then may the verified temporary backup atomically replace
`/.chaos-update/rollback`. The older rollback is deleted only after the new
rollback verifies in its retained location.

## Installation and migrations

Core installation must follow a dedicated Core installation plan. It must not
copy a Core archive using the module updater's controller, model, and view
destination rules.

After staging and preflight, installation proceeds in this order:

1. Acquire the Core lock.
2. Create and verify the previous-Core backup.
3. Enter Core maintenance state.
4. Place every new Core file using temporary files followed by same-filesystem
   replacement, withholding `app/core/version.php` as the final version commit.
5. Remove paths owned by the previous Core manifest but absent from the new
   manifest.
6. Run the release's unapplied migrations in their declared order.
7. Rebuild required Core-generated files.
8. Verify the installed Core.
9. Install and verify `app/core/version.php` last.
10. Exit maintenance state, retain one rollback, and release the lock.

Every replacement and removal must be recorded in the operation journal so
restoration can reverse it. A file not owned by the previous Core manifest is
never treated as obsolete.

Database migrations are forward-only, recorded exactly once, and
transactional wherever the database supports the required operation. Every
automatic migration must remain compatible with both the new Core and the one
retained previous Core version. Destructive changes that prevent the previous
Core from running are prohibited within that rollback window. A failed
migration transaction must be rolled back before filesystem restoration.
Core rollback does not restore a stale database snapshot over newer site data.

## Post-install verification

The Core Updater must verify the installed system before committing success.
Verification must be safe to run while the updater controls the installation
state and must not depend solely on the target version string.

Mandatory verification must:

- verify every installed Core file against the signed manifest;
- confirm that obsolete Core-owned files are absent;
- run PHP syntax validation on every installed Core PHP file;
- confirm that `app/core/config.php` remains readable and byte-for-byte
  unchanged;
- confirm that the operation journal contains no `/public` changes;
- confirm that every required migration is recorded as successful;
- load `app/bootstrap.php` through a dedicated non-web health-check process;
- verify minimum router, base controller, base model, database connection, and
  framework initialization;
- install `app/core/version.php` last and confirm that `CHAOS_VERSION` equals
  the offered version.

A signed release may add named health checks but may not weaken or skip these
mandatory checks.

A failed verification is an installation failure and must enter the approved
Core recovery path.

## Administrative experience

The Core Updater interface must present these server-authoritative states:

- installed Core version and last check time;
- checking for a Core update;
- no update available;
- Core update available, with installed and offered versions;
- update blocked by a named preflight or compatibility condition;
- downloading;
- verifying package;
- staging;
- preparing backup;
- installing Core files;
- applying Core migrations, when applicable;
- verifying installation;
- restoring the previous Core release;
- update complete;
- update failed before live changes;
- update failed and previous release restored;
- update failed and manual recovery required.

While the Core system is changing, the interface must disable conflicting
Core updater actions and must not infer progress or success independently of
the server.

Core maintenance mode starts immediately before live Core changes. Public
requests receive HTTP `503 Service Unavailable` with `Retry-After` until the
new or restored Core verifies successfully. Authenticated administrators are
never blocked by Core maintenance mode and retain access to administration and
update-recovery controls. The administration interface must clearly display
the active maintenance state and suppress unrelated conflicting state changes
where practical.

Maintenance state is stored in `/.chaos-update/` and remains active through
installation, migrations, verification, and restoration. A timeout alone must
not reopen public access. Incomplete recovery keeps public maintenance active
while administrator recovery remains available.

Automatic updating, unattended scheduling, bulk updating, alternate release
channels, downgrade, reinstall, and arbitrary package upload are not created
by these requirements.

## Result and error contract

Every Core update request must return a stable outcome, current installed Core
version, target version when applicable, current or failed phase, and a stable
error code when unsuccessful.

The completion outcomes are:

- `up_to_date`;
- `update_available`;
- `updated`;
- `failed_unchanged`;
- `failed_restored`;
- `failed_recovery_required`;
- `update_in_progress`.

Implementation-specific error codes must distinguish authority, metadata,
package, compatibility, preflight, lock, backup, filesystem, migration,
verification, restoration, and cleanup failures without exposing secrets or
unsafe filesystem details.

## Release-gate verification

The v1.1.9 Core Updater gate requires automated fixtures or controlled test
packages covering at least:

- no Core update available;
- valid Core update;
- untrusted or invalidly signed Core metadata;
- Core package integrity failure;
- malformed, unsafe, or structurally invalid Core package;
- compatibility or preflight failure before live changes;
- concurrent Core update request;
- backup creation or verification failure;
- interrupted or partial filesystem installation;
- Core migration failure, when migrations are in scope;
- post-install verification failure;
- successful verified restoration;
- incomplete restoration requiring manual recovery;
- successful update followed by a new Core update check;
- proof that module updater authority and state cannot authorize or complete a
  Core update;
- proof that a Core update does not rewrite independently versioned module
  state unless the approved Core package contract explicitly owns a named
  Core-managed file.

## Manual recovery

Chaos MVC must provide both an administrator recovery screen and a standalone
command-line fallback.

`/.chaos-update/` contains the lock, operation journal, failure report, staged
manifest, and retained previous-Core backup. The administrator recovery screen
must show the installed and target versions, failed phase, changed files,
migration state, restoration state, and the next safe action. An administrator
may retry verified restoration of the one retained previous Core.

If normal administration cannot load, the command-line recovery utility must
operate without bootstrapping the damaged application. It may verify and
restore only the retained trusted backup; it may not download or install a new
release. Recovery actions require confirmation and are added to the operation
journal.

Public maintenance remains active until restoration verification passes.
Successful recovery reports the previous Core version and retains the failed
update report for diagnosis. Neither recovery path may modify `/public`, module
state, or `app/core/config.php`.

## Authoritative decisions

The twelve Core-specific release-gate decisions are resolved in this document:

1. `chaos-mvc.org` operates the official HTTPS Core update service.
2. One fixed embedded Core signing key establishes release authority.
3. The service offers one applicable stable release.
4. Releases are complete, signed-manifest Core packages.
5. `/public` and `app/core/config.php` are outside Core update ownership.
6. Core lock and recovery state is filesystem-based in `/.chaos-update/`.
7. Exactly one previous Core backup is retained for rollback.
8. Migrations are forward-only and one-version backward compatible.
9. Installation and obsolete-file removal are signed-manifest driven.
10. Success requires mandatory filesystem, migration, bootstrap, and version
    verification.
11. Public requests enter maintenance while authenticated administrators remain
    available.
12. Recovery is available through administration and a standalone command-line
    fallback.
<!-- [End AI:GPT-5.6 Sol] -->
