# Digit Chat 0.3.0 — project-isolated persistence

## Scope

ChAoS MVC owns the authenticated account, project, conversation, message history
and SQL lifecycle. Digit Web Transport still polls /chat/transport using its
existing outbound HTTP contract. Digit Core and framework Core are unchanged.

**Absolute context isolation:** Only the selected account + project +
conversation may supply past messages to Digit. Conversations within the same
project remain separate. No global search, account-wide history, other
project messages, or inferred fallback is allowed.

## Install

1. Update the Chat module files from this release.
2. Open /admin/chat as Level 9. The module must report the database as missing.
3. Submit **Install SQL** with the existing CSRF control.
4. Verify all four Chat-owned tables exist and the admin view reports current.
5. Open /chat, create a project, then create a conversation and send a message.

The module does not install SQL on a visitor request. The SQL file is
user/modules/chat/sql/schema.sql. The module manifest declares all four owned
tables for ChAoS MVC's module lifecycle and Nuke.

**Cutover:** The previous 0.2.x temporary-file queue is not imported or
read by 0.3.0. Do not cut over while previous requests are still in flight.
There is no automatic recovery of file-backed conversation data, because 0.2.x
did not persist it as conversation history.

## Conversation contract

- Browser-visible route stays exactly **/chat**. Project and conversation IDs
  are sent in CSRF-protected POST bodies, never in path segments or query strings.
- GET /chat renders only the authenticated account's projects. The last
  selected conversation is retained in the PHP session and revalidated.
- POST /chat/workspace with explicit allowlisted action creates projects and
  conversations or opens a scoped project/conversation.
- POST /chat queues messages for one explicit account, project and conversation.
  It does not rely on whatever another browser tab selected.
- POST /chat/status uses the session-bound delivery receipt and account.
  The request ID is sent in the POST body, not the URL.
- Digit /chat/transport remains loopback-gated; **loopback is not process
  authentication** and the existing server-side hardening requirement remains.

Context is sourced from the exact selected conversation's recent messages
(maximum 8 recent messages, 180 bytes each) and the same project's optional
context notes. The context envelope fits within the existing 2047-byte native
route limit and preserves UTF-8 boundaries. New conversations and slash commands
retain the established raw-message behavior. Existing identity questions remain
unchanged. For a returning greeting, the Chat model sends a scoped [resume]
envelope; **Digit Ask v1.0.9** renders an evidence-based welcome referencing
the retrieved thread, without Ollama inference. Ordinary contextual prompts
use [conversation] and are handled by the current inference service.

Only the user's original submitted text is saved as their chat message.
The context envelope is transport-only and not displayed as the user's message.
Digit's completed reply is stored in the same authenticated, scoped conversation.

**Important:** This release persists message history. It does not yet implement
semantic summarization of arbitrarily old conversations; the native prompt
uses only recent exchanges. Do not claim months-old details are recalled
unless they appear in the retrieved context.

## Acceptance and security tests

- Level 9 explicitly installs SQL; ordinary GET does not create tables.
- Level 7 requires normal Chat module permission; other users denied.
- Account A must not access Account B's project/conversation through forged IDs.
- Conversation X must not read message history from sibling conversation Y.
- Project A must never retrieve any messages from project B.
- Late replies must be stored against their original account/project/conversation,
  even if the user changes conversations in another tab.
- Sending "hey" in a previously used thread must reference that thread's recent
  history; sending "hey" in a new thread must remain the normal greeting.
- /github commands and direct identity questions must retain normal routing.
- Restart the PHP/web server and log in again. Projects and messages persist;
  selection can be restored manually if the PHP session was replaced.

## Runtime status

Source implementation is committed. Native build, PHP lint, SQL installation,
cross-account isolation tests against a real MySQL database and live acceptance
must be performed before calling this release production-ready.
