# ChAoS MVC Installer Environment & Filesystem Preflight

## Purpose

The ChAoS MVC installer must qualify the environment before installation begins.

Preflight exists to answer three questions:

1. What environment is ChAoS running under?
2. What filesystem capabilities does ChAoS require?
3. Does the running PHP process actually have those capabilities?

Installation eligibility requires both positive identification of a
Foundation-qualified environment and demonstrated runtime capability. Names,
ownership, group membership, and displayed permission modes do not substitute
for an effective capability test.

---

## Environment Identification

Preflight should identify and report the runtime environment where that information is reliably available.

This includes:

- Operating system
- Web server
- Web server version where available
- PHP version
- PHP SAPI
- PHP runtime identity where reliably available

The installer recognizes these Foundation-qualified environments:

- Apache HTTP Server
- LiteSpeed
- OpenLiteSpeed
- nginx
- Windows

Environment identification is a mandatory installation gate. If the operating
environment cannot be positively identified as Windows, Apache HTTP Server,
nginx, LiteSpeed, or OpenLiteSpeed, the installer must report the failure and
refuse installation before making installation changes.

---

## Capability Validation

ChAoS must not assume that a filesystem resource is writable merely because its displayed permissions appear appropriate.

For example, a directory with mode `0775` may still be unwritable by PHP when the PHP runtime user does not own the directory and is not a member of its owning group.

Preflight must therefore test the effective capabilities of the running PHP process.

Where required, qualification should determine whether PHP can:

- Read an existing resource
- Write an existing resource
- Create a resource within a directory
- Write to a newly created test resource
- Remove a test resource created by preflight

Permission modes, ownership, and runtime identity may be reported for
diagnostic purposes. After the environment gate passes, demonstrated
capability determines the capability PASS or FAIL result.

---

## Required Filesystem Capabilities

ChAoS must maintain an explicit list of filesystem resources that require runtime access.

Known requirements currently include:

### Installation-Time Resources

- `app/core/config.php`
  - Must be writable when installation or configuration requires modification.

- Installer lock location
  - Must permit creation of the installation lock.

### Runtime Resources

- `app/data/`
  - Must permit ChAoS-owned runtime data to be created and updated.

- `logs/`
  - Must permit ChAoS logging when logging is enabled.

- `user/pages/`
  - Must permit creation, modification, and lifecycle operations for filesystem-backed Pages.

- `user/modules/`
  - Must permit module installation, update, rollback, and removal operations.
  - Must permit creation and replacement of module-owned JSON and other
    filesystem-backed runtime data within an installed module's own directory.
  - Must permit Core to create module maintenance locks, isolated incoming and
    backup directories, and the single retained previous filesystem version.

- `user/themes/`
  - Must permit theme installation, selection support, update, rollback, and
    removal operations.
  - Must permit Core to create theme maintenance locks, staging directories,
    backup directories, and the single retained previous filesystem version.

- `public/`
  - Must permit ChAoS to create and replace its generated public discovery
    artifacts: `site.json`, `sitemap.xml`, `ror.xml`, `llms.txt`, and `rss.xml`.
  - This requirement does not authorize arbitrary executable uploads or make
    the entire public tree an unrestricted user-content destination.

- `public/uploads/`
  - Must permit validated media uploads and media lifecycle deletion.
  - Web-server controls must continue preventing uploaded content from gaining
    executable authority.

### Update-Time Resources

- `app/data/updater/`
  - Must permit Core updater status and maintenance-lock files to be created,
    replaced, and removed.

- `tmp/updater/`
  - Must permit Core update packages and staging content to be created, written,
    read, renamed, and removed.

- `backups/updater/`
  - Must permit the one retained previous Core filesystem version to be created,
    replaced, read, renamed, and removed.

- PHP system temporary directory
  - Must permit isolated module-update workspaces to be created, written, read,
    renamed, and removed by the running PHP process.

### Feature-Dependent Resources

- `releases/`
  - Required when an installed developer Builder generates and retains release
    artifacts. It is not a mandatory writable directory for installations that
    do not provide a Builder capability.

- Module-owned data paths declared by an installed module
  - Required when that module uses filesystem-backed JSON or other runtime data.
    Qualification must remain confined to the module's own directory and must
    not grant that module general write authority over Core or other modules.

- Theme-owned writable paths declared by an installed theme or theme tool
  - Required only when that installed component has an established runtime
    write requirement. Theme rendering alone does not make PHP theme files
    generally writable.

Additional runtime-writable resources must be added to this contract when established through ChAoS development and testing.

---

## Preflight Testing

Preflight must test required capabilities using the same PHP runtime executing ChAoS.

A filesystem qualification result should distinguish the required capability from the demonstrated capability.

Example:

    ChAoS MVC Installation Preflight

    Web Server:       Apache/2.4.66
    PHP Version:      8.5.4
    PHP SAPI:         apache2handler
    Runtime User:     www-data
    Operating System: Linux

    Filesystem Qualification

    [PASS] app/data/                 CREATE / WRITE
    [PASS] app/core/config.php       WRITE
    [FAIL] logs/                     CREATE / WRITE
    [FAIL] user/pages/               CREATE / WRITE
    [FAIL] user/modules/             CREATE / WRITE / RENAME / DELETE
    [PASS] user/themes/              CREATE / WRITE / RENAME / DELETE
    [FAIL] public/ generated files   CREATE / WRITE / REPLACE
    [PASS] installer lock location   CREATE / WRITE

If a safe temporary resource is created during qualification, preflight must remove that resource after the test.

Preflight testing must not alter existing installation content merely to determine whether a capability exists.

---

## Installation Failure

Installation must not continue when a mandatory capability fails qualification.

The failure must identify:

- The resource that failed
- The capability ChAoS requires
- The capability that could not be demonstrated
- Relevant runtime information where available
- What the administrator needs to correct

Example:

    ChAoS MVC cannot continue installation.

    PHP does not have the filesystem access required by this installation.

    Runtime User: www-data

    Failed Resources:

    logs/
        Required: CREATE / WRITE
        Result:   FAILED

    user/pages/
        Required: CREATE / WRITE
        Result:   FAILED

    Correct the filesystem access available to the PHP runtime and run preflight again.

    No installation changes have been made.

---

## Permission Safety

ChAoS must not attempt to obtain elevated operating-system privileges in order to satisfy preflight.

Preflight must not:

- invoke `sudo`;
- attempt privileged ownership changes;
- broadly loosen filesystem permissions;
- recursively make the ChAoS installation world-writable; or
- assume a particular web-server user or group.

In particular, ChAoS must never use `0777` as a generic solution to a failed filesystem qualification.

Where useful, ChAoS may report observed ownership, permissions, and runtime identity to help the administrator diagnose the failure.

The administrator remains responsible for authorizing operating-system permission and ownership changes.

---

## Foundation-Qualified Environments

ChAoS requires both positive environment identification and demonstrated
runtime capability. Neither substitutes for the other.

Windows is identified from the PHP operating-system family. Apache2, nginx,
LiteSpeed, and OpenLiteSpeed are identified from web-server and PHP-SAPI
evidence available to the running request. Server identity alone never proves
filesystem authority, and filesystem capability alone never authorizes an
unknown environment.

An unknown environment must fail closed. Support for another environment must
be established by the ChAoS Foundation before the installer may recognize it.

---

## Post-Installation Diagnostics

The filesystem qualification mechanism should be reusable after installation.

A future ChAoS diagnostic interface may use the same capability definitions and tests to determine whether required runtime access has changed since installation.

This allows ChAoS to distinguish application failures from environmental failures caused by later changes to:

- ownership;
- permissions;
- PHP runtime configuration;
- web-server configuration; or
- deployment environment.

The installer and post-install diagnostics should use the same authoritative capability contract.

---

## Qualification Principle

ChAoS must identify a Foundation-qualified environment and determine whether
that environment provides every required capability.

The preflight sequence is therefore:

    Identify Environment
            ↓
    Load ChAoS Requirements
            ↓
    Test Effective Capabilities
            ↓
       PASS / FAIL
            ↓
    Install or Report

Environment identification informs the administrator.

Demonstrated capability determines whether installation may proceed.
