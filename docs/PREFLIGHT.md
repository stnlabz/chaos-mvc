# ChAoS MVC Installer Environment & Filesystem Preflight

## Purpose

The ChAoS MVC installer must qualify the environment before installation begins.

Preflight exists to answer three questions:

1. What environment is ChAoS running under?
2. What filesystem capabilities does ChAoS require?
3. Does the running PHP process actually have those capabilities?

Installation eligibility is determined by demonstrated runtime capability, not assumptions about the operating system, web server, ownership, group membership, or displayed permission modes.

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

Known web-server environments may include:

- Apache HTTP Server
- LiteSpeed
- OpenLiteSpeed
- nginx
- Caddy
- Microsoft IIS
- Other or unknown web servers

Web-server identification provides diagnostic context.

It does not determine whether ChAoS can be installed.

An unknown or unrecognized web server must not, by itself, fail preflight.

---

## Capability Is Authoritative

ChAoS must not assume that a filesystem resource is writable merely because its displayed permissions appear appropriate.

For example, a directory with mode `0775` may still be unwritable by PHP when the PHP runtime user does not own the directory and is not a member of its owning group.

Preflight must therefore test the effective capabilities of the running PHP process.

Where required, qualification should determine whether PHP can:

- Read an existing resource
- Write an existing resource
- Create a resource within a directory
- Write to a newly created test resource
- Remove a test resource created by preflight

Permission modes, ownership, runtime identity, and server information may be reported for diagnostic purposes, but demonstrated capability determines PASS or FAIL.

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

## Web Server Independence

ChAoS must remain web-server independent.

Known server environments may receive more specific diagnostic information, but installation behavior must not depend upon a fixed list of supported web servers.

The governing rule is:

> Server identification is telemetry. Effective capability testing is authority.

For example, an installation running under an unknown server may report:

    Web Server:   Unknown
    PHP SAPI:     fpm-fcgi

If every mandatory ChAoS capability passes qualification, the unknown server identity does not prevent installation.

This allows ChAoS to operate correctly on established platforms as well as environments not known when the installer was developed.

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

ChAoS does not need to understand every web server in existence.

It needs to understand what ChAoS requires and determine whether the environment can provide it.

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