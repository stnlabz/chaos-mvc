# Digit Chat — ChAoS MVC user module

**Route:** `/chat` (native router, no aliases or Core changes)

**Status:** Source-level implementation, deliberately disabled.
Digit Web Transport native qualification and activation are still pending.

## Architecture

- Controller: Level-9-only access, request flow, CSRF and response presentation.
- Model: local Unix stream socket and complete binary framing.
- View: server-rendered chat, escaped text; no browser-to-Digit transport.
- Database: none (`database_tables: []`).
- Public access: not enabled; unauthenticated and lower-level users cannot
  use the interface. Current access policy is intentionally restrictive.

## Protocol contract

Request: unsigned 32-bit big-endian byte count (1–2047), then UTF-8 bytes.

Response: status byte (0 success, 1 error), unsigned 32-bit big-endian byte
count (0–511), then UTF-8 bytes. Reads/writes loop across partial transfers;
stream operations use bounded timeouts. Status 1 is treated as failure; no
unqualified extended reply or Digit authentication is presumed.

The Unix socket path is server-side configuration and must never be
rendered to the visitor. Backend errors are not surfaced to end users.

## Qualification and activation

Before enabling `chat_model::ENABLED`, an authorized operator must verify:
1. Digit's native web transport is qualified and actively serving the agreed
   Unix socket framed protocol.
2. The PHP worker can access that socket under correct least-privilege
   Unix filesystem permissions.
3. Request/reply framing with partial I/O, timeouts, incorrect lengths,
   invalid UTF-8, status 1, and peer disconnection.
4. CSRF and access controls, including unauthenticated and unauthorized users.
5. Practical end-to-end exchanges and explicit Director authorization.

Only after this evidence and authorization, set `ENABLED` to `true` and
deploy through the standard module lifecycle. Do not advertise the interface
or link it in public navigation until formally qualified.

No Core files are modified.
