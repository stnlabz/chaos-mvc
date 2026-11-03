# Digit Chat — ChAoS MVC module 0.2.0

## Integration
Digit Web Transport v1.0.1 initiates local HTTP POST to `http://127.0.0.1/chat/transport`. PHP does not contact Digit or use a Unix socket. Digit polls with `{"type":"poll"}`, receives one `{"id":"...","message":"...","display_name":"..."}` or HTTP 204, and POSTs a `{"type":"reply","id":"...","status":"ok|error","message":"..."}` response. An acknowledged reply receives HTTP 200. Digit Core routing and ABI remain unchanged. The authorized ChAoS MVC login controller now includes the existing account `display_name` in the PHP session.

`/chat`: Level 7+ with existing module permission authorized GET interface and CSRF-checked POST enqueue (HTTP 202 JSON, request ID).

`/chat/status?id=...`: Level 7+ with existing module permission authorized GET response retrieval, scoped to the originating website session. Other sessions cannot read the response.

`/chat/transport`: POST-only JSON operation, currently gated to source address 127.0.0.1. Does not use website session authentication because Digit's native transport is a background process. **Loopback address alone does not authenticate the Digit process.** Before accepting deployment, restrict access to this route at the web-server boundary and verify no other local users/processes can impersonate Digit. Digit v1.0.1 does not send a shared secret or signed authentication header; robust transport-origin authentication requires a separately authorized contract change.

## Queue
Small, short-lived file queue in a private, 0700 per-site directory under the PHP temporary directory, with a 0600 JSON file and `flock` serialization. Request IDs are random. A message is atomically transitioned from queued to processing on first poll; duplicate replies are rejected. Expired entries are cleaned on subsequent requests (90-second TTL). Maximum 100 queue entries. Queue data is ephemeral and is not durable conversation history. Do not run chat across multiple independent PHP hosts or isolated /tmp directories without changing the storage architecture by authorization.

The browser uses fetch to submit, then polls for results without a page refresh. The interface distinguishes queued, processing, completed and failed delivery states. Only a completed status=ok result is rendered as a successful Digit reply. It displays text with `textContent`, not HTML.

## Requirements and limits
- Browser message: 1 to 2047 UTF-8 bytes, no ASCII control characters; Digit's parser does not accept embedded newlines.
- Digit reply: up to 511 UTF-8 bytes.
- One request per native transport poll. No streaming or persistent history.
- Verify that `http://127.0.0.1/chat/transport` resolves to the intended ChAoS MVC virtual host; Digit does not use the public hostname by default.
- Verify that Digit v1.0.1 Web Transport has been built, qualified, installed, and activated on the server.
- Test negative access, CSRF, session isolation, queue roundtrip, and error behavior. Source commits are not live acceptance evidence.

Scope of the display-name enhancement: `app/controllers/auth.php` (authorized login session assignment), Chat module queue/controller, and Digit native Web module sender mapping. Digit Core is unchanged.

## Source-review findings

Digit's IRC and Web modules both invoke `digit.router.dispatch`. The Ask module owns greetings and ordinary conversation. Web transport uses the authenticated account display name as the router sender, falling back to `digit-web` for legacy queued jobs; IRC forwards its IRC nickname; neither transport should synthesize a reply. Digit Web now reports changing HTTP polling failures in its journal without logging message bodies. This identifies wrong virtual host/404, denial/403, and malformed job payload/200 during deployment verification. The native module is still responsible for activation and HTTP reachability.

The Level 7 account accesses Chat through ordinary ChAoS MVC login with explicit Chat module permission. This integration does not implement automated login or change Core authentication.

## Display-name identity boundary

`display_name` is read from the authenticated PHP session, not browser-submitted form data. Existing authenticated sessions without the field load it once by account ID from the existing `accounts` table and cache it in the session. A blank configured display name falls back to the account username. The queue includes only the display name, message and correlation ID in the Digit poll result; session hash stays server-side. Digit passes this name as `digit_route_request_t.sender`, while leaving privileged `account` empty. It is a conversational label, **not authorization or an authenticated Digit account assertion**. New login sessions receive the field via `app/controllers/auth.php`.
