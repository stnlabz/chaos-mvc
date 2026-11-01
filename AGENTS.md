# ChAoS MVC — Repository Agent Instructions

These instructions govern automated contributions to ChAoS MVC.

## Engineering Principles

- Keep changes small, deterministic, understandable, and compatible with existing Core architecture.
- Do not introduce new release-security machinery or publishing requirements without explicit project-owner authorization.
- Preserve module/theme installation boundaries, existing permission checks, and safe update rollback.
- Do not claim a feature has passed live testing without observed evidence.

## Module and Theme Updates — Authoritative Policy

**RSA-based release signatures are NOT authorized for module or theme updates.**

- Do **not** introduce or reinstate RSA-SHA256 signature verification, RSA key-size enforcement, PEM public/private keys, publisher key IDs, or mandatory cryptographic release signatures in the module/theme update workflow.
- SHA-256 checksums **are authorized and required** for downloadable module/theme ZIP integrity verification. SHA-256 is a hash, **not** an RSA signature and does not authenticate the publisher.
- Remote update manifests may declare the ZIP checksum as a top-level `sha256` or as `signing: {"algorithm":"sha256","sha256":"<64 lowercase or uppercase hex digits>"}`. If both are present, their values must match. In this established format, the field name `signing` means *checksum metadata only*.
- Follow the uncomplicated lifecycle: read the installed module/theme's configured HTTPS manifest, compare versions, download the ZIP, verify its SHA-256, safely extract and stage it, replace the owned module/theme atomically, and clean up temporary files. Preserve available rollback.
- Reject missing or mismatched checksums, unsafe ZIP entries, paths outside the owned module/theme, mismatched module/theme identity, or inconsistent release versions.
- Keep HTTPS transport, authorized download-host restrictions, archive safety checks, and existing Core permission boundaries.
- The module updater must support ZIP roots named `{module}/` or `{module}-{version}/` and must install under the canonical unversioned module directory.
- Do not silently substitute digital signatures for checksums, or change this policy without explicit project-owner approval.

## Evidence and Scope

- A checksum proves the downloaded ZIP matches the *manifest's claimed bytes*; it does not establish publisher identity if both the manifest and ZIP are compromised.
- Source review is not equivalent to live installation, update, or rollback testing. Record test status accurately.
- Update `docs/CHANGELOG.md` when changing module/theme update behavior.
