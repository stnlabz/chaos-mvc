# Module and theme release signatures

Both Core updaters use the same verification rules. SHA-256 is a package
checksum, never a publisher signature or fallback authentication method.

Local `signing` contains `algorithm`, `key_id`, and `public_key`.
`algorithm` accepts `rsa-sha256` or `openpgp` (`pgp` is also accepted).
Builder field `type` is accepted instead of `algorithm`; if both occur they
must agree. The locally installed algorithm and key remain authoritative.

`public_key` is base64-encoded public PEM for RSA or base64-encoded OpenPGP
public key data. Existing unencoded RSA PEM and armored PGP public keys are
also accepted. RSA keys must be at least 3072 bits. PGP requires PHP's GnuPG
extension version 1.5+ and its GnuPG backend on the installation. Without it,
PGP updates fail explicitly; RSA updates do not require GnuPG.

The remote JSON retains `module` or `theme`, `version`, `download`, `sha256`,
`key_id`, and `signature`. `signature` is base64-encoded detached signature
bytes, not a filename, public key, checksum, or URL.

Sign these exact UTF-8 lines joined with LF, with **no trailing newline**:

```text
CHAOS-MVC-MODULE-RELEASE
module=<slug>
version=<version>
download=<exact download URL>
sha256=<lowercase package SHA-256>
key_id=<locally trusted key ID>
```

For themes replace the first line with `CHAOS-MVC-THEME-RELEASE` and the
second with `theme=<slug>`. All remaining fields and encoding rules match.
RSA uses SHA-256 with PKCS#1 v1.5 signing. PGP uses a detached signature of
the same statement. Signing only the ZIP checksum is not compatible.

PGP verification imports exactly one publisher public key into a new private
temporary keyring, checks the signing fingerprint against that key's valid
subkeys, rejects invalid/revoked/expired signatures, then removes the keyring.
It does not use the account's default keyring or download publisher keys.

Builders must implement this contract before their artifacts will verify.
