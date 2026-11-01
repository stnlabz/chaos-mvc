# CDM — Core Diagnostic Service

CDM is the protected ChAoS MVC diagnostic service available to authority-level
9 administrators at `/admin/cdm`.

## Scope

- Passive observations of the active ChAoS version, PHP runtime, SAPI,
  web-server telemetry, runtime account, operating system, required PHP
  extensions, session controls, required Core files, runtime paths, and
  generated public resources.
- CSRF-protected capability probes for effective filesystem operations, ChAoS
  log delivery, and a prepared database read.
- Bounded inspection of installed module metadata and declared source files
  without including or executing module PHP.
- No automatic repairs, schema mutations, package installation, Core updates,
  arbitrary command execution, or disclosure of protected configuration.

## Result classifications

- `OBSERVED` — directly read from the active runtime or filesystem.
- `DEMONSTRATED` — established through a bounded controlled operation.
- `UNVERIFIED` — the runtime could not establish the condition.
- `FAILED` — a required controlled operation failed.
- `CONFIRMED` — static inspection established a defect.
- `POTENTIAL MISMATCH` — source and metadata may disagree, but execution was
  not performed.

## Authority boundary

Both CDM entry points require authority level 9. CDM is registered as a
protected Core component and cannot be assigned to level-7 Developer or AI
accounts. Active probes require POST and CSRF verification.

## Probe behavior

Filesystem probes use unpredictable exclusive-create names and attempt cleanup
after success or failure. The logging probe appends a clearly identified
diagnostic record. The database probe performs only a prepared `SELECT 1`.

Module inspection confines reads to a selected installed module, rejects linked
files, bounds file sizes, validates declared paths, parses PHP without executing
it, and distinguishes confirmed contract failures from heuristic mismatches.

CDM does not display database credentials, private keys, session identifiers,
CSRF tokens, cookies, environment secrets, or complete configuration files.

## Interpretation

Server headers, ownership, permission bits, and configuration values are
telemetry. They do not prove effective capability. A successful bounded probe
demonstrates only the tested operation through the active runtime.

No CDM finding does not establish that an installation or module is correct,
secure, qualified, or production-ready. Failures occurring before PHP executes
remain within the web-server or SAPI logging boundary.
