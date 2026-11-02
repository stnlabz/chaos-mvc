# Retired Module and Theme Signature Contract

The former RSA-SHA256 and OpenPGP module/theme release-signature contract is
not part of the current ChAoS MVC updater architecture.

Module and theme updates now require HTTPS retrieval, authorized package-host
confinement, archive validation, and SHA-256 verification of the downloaded
ZIP. The checksum may appear at the manifest root or in the established nested
`signing` checksum shape. A SHA-256 checksum verifies package integrity against
the manifest but does not authenticate the publisher.

This file remains only to prevent older documentation links from silently
leading developers to an absent document. Do not generate RSA keys, publisher
key IDs, PEM metadata, or detached signatures for the current module/theme
update workflow.

See `MODULE_CREATION_GUIDE.md`, `THEMES.md`, and
`THEME_UPDATE_CONTRACT.md` for the current contracts.
