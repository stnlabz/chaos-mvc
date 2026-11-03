# Digit Chat 0.4.0 — project-isolated persistence

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

## 0.3.1 — Project-owned chat navigation (2026-11-03)

The sidebar now displays chats directly beneath their parent project instead of in a visually detached global conversation list. Each project row has its own **+ Chat** action, explicitly creating a conversation under that project's ID through the existing CSRF-protected workspace endpoint. Creating a project selects it; its empty state points to the per-project chat action. Selecting a chat stays at `/chat` with identifiers only in authenticated POST bodies. No SQL migration, account scope modification, or Digit native transport change was necessary.

An existing conversation remains assigned to the project under which it was originally created; this version does not silently move or merge chats between projects.

## 0.4.0 — GitHub chat assignment and Example-style SQL lifecycle

**Upgrade existing installations through /admin/chat, not manual SQL.**
The Chat module upgrades schema 1.0.0 to 1.1.0 using
`sql/patches/1.0.0-to-1.1.0.sql`, after a Level 9 account
explicitly submits **Update SQL** (POST plus CSRF). Previously installed
0.3.x schemas have four Chat tables but no version table; the model
recognizes that specific legacy layout as schema 1.0.0. A partial or
unknown layout reports **invalid**, never auto-migrates. Fresh installs
create the versioned 1.1.0 layout directly. The new `chat_schema`
table is declared in `module.json` for the Core-owned Nuke lifecycle.

Once current, select an individual chat and use **Assign repository**.
Enter one GitHub `owner/repo` (e.g., `stnlabz/chaos-mvc`).
The binding is stored on that particular `chat_conversations` row.
Other chats and projects are not modified or searched. Clearing the
field removes the assignment.

The Chat module supports a small deterministic, read-only command vocabulary:
`repo summary`, `repo tree`, `repo tree modules`, `repo commits`,
`repo issues`, `repo prs`, `repo file README.md`, and
`repo inspect user/modules/chat/models/chat_model.php`.
These are rewritten server-side to `/github <assigned owner/repo> <operation>`
through the existing Digit GitHub module. A direct raw `/github owner/repo`
message is rejected by Chat to prevent bypassing the chat assignment.
Digit's separate production `repos.conf` allowlist must also approve
the repository; a Chat assignment does not expand its privileges.

This release is **read-only**: no checkout, patch, commit, PR creation,
private GitHub access, autonomous engineering, or cross-conversation
memory transfer. The URL remains `/chat`. Existing messages survive
the supported migration; no automated deployment or SQL execution is
performed by GitHub commits.
