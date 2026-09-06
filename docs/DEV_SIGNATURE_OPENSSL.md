# Chaos MVC Release Signing with OpenSSL

This guide documents the RSA/SHA-256 release-signing workflow for Chaos MVC theme and module releases using OpenSSL.

## Purpose

Chaos MVC release verification uses two different cryptographic values:

- `sha256` — the SHA-256 checksum of the exact release ZIP.
- `signature` — a Base64-encoded detached RSA/SHA-256 signature over the exact canonical release statement.

The SHA-256 checksum is **not** the release signature.

## Installed Theme Trust

The installed theme's `theme.json` pins the trusted signing identity:

```json
{
    "signing": {
        "algorithm": "rsa-sha256",
        "key_id": "developer-key-id",
        "public_key": "<BASE64 OF FULL PUBLIC PEM>"
    }
}
```

The installed theme does not need the release checksum or detached release signature in its `signing` block.

## Remote Theme Release Manifest

A remote theme release manifest contains the release identity and signature:

```json
{
    "theme": "example-theme",
    "version": "1.2.3",
    "download": "https://example.com/downloads/example-theme",
    "sha256": "<LOWERCASE SHA-256 OF EXACT ZIP>",
    "key_id": "developer-key-id",
    "signature": "<BASE64 DETACHED RSA SIGNATURE>"
}
```

The values used here must exactly match the values used when generating the signature.

## 1. Calculate the Release ZIP SHA-256

Using OpenSSL:

```bat
openssl dgst -sha256 example-theme-1.2.3.zip
```

Example output:

```text
SHA2-256(example-theme-1.2.3.zip)= 0123456789abcdef...
```

Use the lowercase hexadecimal checksum in the release manifest and canonical release statement.

## 2. Create the Canonical Theme Release Statement

Create a text file such as:

```text
example-theme-1.2.3-release.txt
```

Its contents must be exactly:

```text
CHAOS-MVC-THEME-RELEASE
theme=example-theme
version=1.2.3
download=https://example.com/downloads/example-theme
sha256=<LOWERCASE SHA-256 OF EXACT ZIP>
key_id=developer-key-id
```

Requirements:

- UTF-8 text
- LF line endings
- No trailing newline after the final `key_id=` line
- The `download=` value must exactly match the remote release manifest
- The `sha256=` value must exactly match the release ZIP
- The `key_id=` value must match the locally trusted key ID

Any byte-level difference changes the signature.

## 3. Sign the Release Statement

Use the RSA private key that corresponds to the public key pinned by the installed theme:

```bat
openssl dgst -sha256 -sign "C:\path\to\private-key.pem" -out "example-theme-1.2.3-release.sig" "example-theme-1.2.3-release.txt"
```

The output `.sig` file contains binary detached signature bytes.

Do not publish or upload the private key.

## 4. Verify the Signature Locally

Verify the detached signature with the corresponding public key:

```bat
openssl dgst -sha256 -verify "public-key.pem" -signature "example-theme-1.2.3-release.sig" "example-theme-1.2.3-release.txt"
```

Expected result:

```text
Verified OK
```

If verification fails, do not publish the signature.

Check that:

- the private and public keys match;
- the release statement has not changed;
- the download URL is exact;
- the ZIP checksum is exact;
- the key ID is exact;
- there is no trailing newline or line-ending change.

## 5. Base64-Encode the Detached Signature

On Windows PowerShell:

```powershell
[Convert]::ToBase64String(
    [IO.File]::ReadAllBytes("example-theme-1.2.3-release.sig")
)
```

The returned Base64 string is the value placed in the remote manifest's:

```json
"signature": "<BASE64 DETACHED RSA SIGNATURE>"
```

The signature field does **not** contain:

- the ZIP SHA-256;
- the public key;
- the private key;
- a PEM file;
- an OpenPGP public key.

It contains only the Base64 representation of the detached RSA signature bytes.

## 6. Publish the Remote Release Manifest

Example:

```json
{
    "theme": "example-theme",
    "version": "1.2.3",
    "download": "https://example.com/downloads/example-theme",
    "sha256": "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef",
    "key_id": "developer-key-id",
    "signature": "<BASE64 DETACHED RSA SIGNATURE>"
}
```

Do not change `theme`, `version`, `download`, `sha256`, or `key_id` after signing.

If any of those values change, rebuild the canonical release statement and generate a new signature.

## Module Releases

Module releases use the same procedure, but the canonical statement begins with:

```text
CHAOS-MVC-MODULE-RELEASE
module=example-module
version=1.2.3
download=https://example.com/downloads/example-module
sha256=<LOWERCASE SHA-256 OF EXACT ZIP>
key_id=developer-key-id
```

The signing and verification commands are otherwise the same.

## Quick Reference

Sign:

```bat
openssl dgst -sha256 -sign private-key.pem -out release.sig release.txt
```

Verify:

```bat
openssl dgst -sha256 -verify public-key.pem -signature release.sig release.txt
```

Base64 on PowerShell:

```powershell
[Convert]::ToBase64String(
    [IO.File]::ReadAllBytes("release.sig")
)
```

## Release Verification Model

```text
Release ZIP
    |
    +--> SHA-256 checksum
    |
    +--> canonical release statement
             |
             +--> RSA/SHA-256 signing with private key
                      |
                      +--> detached signature
                               |
                               +--> Base64
                                        |
                                        +--> remote manifest "signature"

Installed theme/module
    |
    +--> trusted algorithm
    +--> trusted key_id
    +--> trusted public_key
             |
             +--> Chaos MVC verifies the remote release signature
```

**Secure the Core. Grow outwards.**
