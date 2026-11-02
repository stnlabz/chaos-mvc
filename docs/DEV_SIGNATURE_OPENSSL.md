# Retired OpenSSL Module and Theme Signing Guide

The OpenSSL RSA signing workflow previously documented here is retired and is
not accepted by the current ChAoS MVC module/theme updater contract.

Current releases use an HTTPS manifest and a required SHA-256 checksum for the
downloaded ZIP. SHA-256 is an integrity checksum, not publisher authentication.
No RSA private key, public PEM, key ID, or detached signature is required.

See `MODULE_CREATION_GUIDE.md` and `THEME_UPDATE_CONTRACT.md` for the active
package and update requirements.
