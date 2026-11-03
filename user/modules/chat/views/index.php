<?php
if (!theme::render('head', get_defined_vars())) {
    require APPROOT . '/views/inc/head.php';
}
?>
<style>
#digit-chat{max-width:1200px;margin:1.5rem auto;padding:0 1rem;color:#e9eff7}
#digit-chat .dc-shell{display:grid;grid-template-columns:290px minmax(0,1fr);border:1px solid #344153;border-radius:16px;overflow:hidden;background:#111a26;box-shadow:0 12px 34px rgba(0,0,0,.16);min-height:65vh}
#digit-chat .dc-sidebar{background:#192636;border-right:1px solid #344153;padding:18px;min-width:0}
#digit-chat .dc-heading{display:flex;align-items:center;justify-content:space-between;gap:10px}
#digit-chat h1{font-size:1.4rem;margin:0;color:#f4f7fb}
#digit-chat h2{font-size:.85rem;text-transform:uppercase;letter-spacing:.06em;margin:18px 0 10px;color:#a8b8ca}
#digit-chat .dc-list{display:flex;flex-direction:column;gap:6px}
#digit-chat .dc-item{width:100%;text-align:left;background:transparent;color:#dce7f2;border:1px solid transparent;white-space:normal;overflow-wrap:anywhere;padding:10px 12px;border-radius:9px;cursor:pointer;font:inherit}
#digit-chat .dc-item:hover,#digit-chat .dc-item[aria-current="true"]{background:#31455b;border-color:#4d6076}
#digit-chat .dc-chat{min-width:0;display:flex;flex-direction:column}
#digit-chat .dc-head{padding:18px 24px;border-bottom:1px solid #344153}
#digit-chat .dc-head-title{font-weight:700}
#digit-chat .dc-sub{font-size:.8rem;color:#a8b8ca}
#digit-chat .dc-history{flex:1;min-height:280px;max-height:62vh;overflow-y:auto;padding:22px;display:flex;flex-direction:column;gap:16px}
#digit-chat .dc-empty{margin:auto;text-align:center;color:#9fb1c5;max-width:27rem;line-height:1.6}
#digit-chat .dc-entry{display:flex;flex-direction:column;gap:5px;max-width:85%}
#digit-chat .dc-entry[data-who="You"]{align-self:flex-end;align-items:flex-end}
#digit-chat .dc-entry[data-who="Digit"]{align-self:flex-start}
#digit-chat .dc-who{font-size:.76rem;color:#a8b8ca}
#digit-chat .dc-bubble{white-space:pre-wrap;overflow-wrap:anywhere;margin:0;padding:12px 16px;border-radius:14px;background:#243246;line-height:1.5}
#digit-chat .dc-entry[data-who="You"] .dc-bubble{background:#285d79;color:#fff}
#digit-chat .dc-entry[data-error="true"] .dc-bubble{background:#422b34;color:#ffd9d9}
#digit-chat .dc-footer{padding:16px 20px;border-top:1px solid #344153}
#digit-chat .dc-state{color:#a8b8ca;min-height:22px;font-size:.82rem;margin:0 0 8px}
#digit-chat .dc-error{color:#ffb9b9;margin:0 0 10px}
#digit-chat .dc-compose{display:flex;align-items:flex-end;gap:10px}
#digit-chat textarea{flex:1;min-width:0;resize:vertical;min-height:65px;max-height:180px;padding:12px 14px;border:1px solid #64748b;border-radius:10px;background:#1c2939;color:#f5f7fa;font:inherit}
#digit-chat .dc-action{background:#80bce1;color:#122638;border:0;border-radius:9px;padding:10px 13px;font-weight:700;cursor:pointer}
#digit-chat .dc-action:disabled{opacity:.6;cursor:wait}
#digit-chat .dc-add{font-size:.82rem;padding:6px 9px}
@media(max-width:700px){#digit-chat .dc-shell{grid-template-columns:1fr}#digit-chat .dc-sidebar{border-right:0;border-bottom:1px solid #344153}#digit-chat .dc-history{max-height:45vh;min-height:260px}#digit-chat .dc-list{max-height:145px;overflow-y:auto}}
</style>
<section id="digit-chat">
<?php if (!empty($data['unavailable'])): ?>
    <p>Digit Chat database is not installed. An administrator must install the Chat schema at <code>/admin/chat</code>.</p>
<?php else: ?>
<div class="dc-shell">
  <aside class="dc-sidebar" aria-label="Projects and conversations">
    <div class="dc-heading"><h1>Digit</h1><button class="dc-action dc-add" id="dc-new-project" type="button">+ Project</button></div>
    <h2>Projects</h2>
    <nav class="dc-list" id="dc-projects" aria-label="Projects"></nav>
    <div class="dc-heading"><h2>Conversations</h2><button class="dc-action dc-add" id="dc-new-conversation" type="button">+ New</button></div>
    <nav class="dc-list" id="dc-conversations" aria-label="Conversations"></nav>
  </aside>
  <div class="dc-chat">
    <header class="dc-head"><div class="dc-head-title" id="dc-title">Select a conversation</div><div class="dc-sub" id="dc-project-title">Conversation history is isolated by project and thread</div></header>
    <div class="dc-history" id="chat-history" role="log" aria-live="polite" aria-label="Conversation"></div>
    <footer class="dc-footer">
      <p class="dc-state" id="chat-status" role="status"></p>
      <p class="dc-error" id="chat-error" role="alert" hidden></p>
      <form id="chat-form" action="/chat" method="POST">
        <?= $this->csrf_field() ?>
        <input type="hidden" id="dc-project-id" name="project_id" value="">
        <input type="hidden" id="dc-conversation-id" name="conversation_id" value="">
        <label for="chat-message" style="display:block;margin-bottom:8px">Message Digit</label>
        <div class="dc-compose">
          <textarea id="chat-message" name="message" maxlength="2047" rows="2" placeholder="Select a conversation first…" required disabled></textarea>
          <button class="dc-action" type="submit" id="chat-send" disabled>Send</button>
        </div>
      </form>
    </footer>
  </div>
</div>
<script>
(() => {
    const initial = <?= json_encode([
        'projects' => $data['projects'] ?? [],
        'project' => $data['project'] ?? 0,
        'conversation' => $data['conversation'] ?? 0,
        'conversations' => $data['conversations'] ?? [],
        'history' => $data['history'] ?? null
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
    const form = document.getElementById('chat-form');
    const projects = document.getElementById('dc-projects');
    const conversations = document.getElementById('dc-conversations');
    const history = document.getElementById('chat-history');
    const status = document.getElementById('chat-status');
    const error = document.getElementById('chat-error');
    const message = document.getElementById('chat-message');
    const send = document.getElementById('chat-send');
    const projectInput = document.getElementById('dc-project-id');
    const conversationInput = document.getElementById('dc-conversation-id');
    let state = initial;
    let busy = false;
    const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

    function showError(text) { error.textContent = text; error.hidden = false; }
    function clearError() { error.hidden = true; error.textContent = ''; }
    function empty(text) {
        history.replaceChildren();
        const p = document.createElement('p');
        p.className = 'dc-empty';
        p.textContent = text;
        history.append(p);
    }
    function addMessage(who, content, failed = false) {
        if (history.querySelector('.dc-empty')) history.replaceChildren();
        const entry = document.createElement('div');
        entry.className = 'dc-entry';
        entry.dataset.who = who;
        if (failed) entry.dataset.error = 'true';
        const label = document.createElement('span');
        label.className = 'dc-who';
        label.textContent = who;
        const bubble = document.createElement('p');
        bubble.className = 'dc-bubble';
        bubble.textContent = content;
        entry.append(label, bubble);
        history.append(entry);
        history.scrollTop = history.scrollHeight;
    }
    function button(label, selected, action) {
        const b = document.createElement('button');
        b.type = 'button'; b.className = 'dc-item'; b.textContent = label;
        b.setAttribute('aria-current', selected ? 'true' : 'false');
        b.addEventListener('click', action);
        return b;
    }
    function render() {
        projects.replaceChildren();
        for (const p of state.projects) {
            projects.append(button(p.title, Number(state.project) === Number(p.id),
                () => workspace('open_project', {project_id: p.id})));
        }
        conversations.replaceChildren();
        for (const c of state.conversations) {
            conversations.append(button(c.title, Number(state.conversation) === Number(c.id),
                () => workspace('open_conversation', {project_id: state.project, conversation_id: c.id})));
        }
        projectInput.value = state.project || '';
        conversationInput.value = state.conversation || '';
        send.disabled = !state.conversation || busy;
        message.disabled = !state.conversation || busy;
        document.getElementById('dc-title').textContent = state.history?.title || 'Select a conversation';
        document.getElementById('dc-project-title').textContent = state.history?.project_title || 'Select a project and conversation';
        if (!state.conversation) {
            empty('Choose or create a conversation to continue with Digit.');
        } else {
            history.replaceChildren();
            for (const m of state.history?.messages || []) {
                addMessage(m.role === 'digit' ? 'Digit' : 'You', m.content);
            }
            if (!history.children.length) empty('This conversation has no messages yet.');
        }
    }
    async function readJson(response) {
        if (!(response.headers.get('content-type') || '').includes('application/json')) {
            throw new Error('Unexpected server response (HTTP ' + response.status + ').');
        }
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.error || 'Request failed.');
        return result;
    }
    async function post(url, values) {
        const body = new FormData(form);
        body.delete('message');
        for (const [key, value] of Object.entries(values)) body.set(key, String(value));
        return readJson(await fetch(url, {
            method: 'POST', body, credentials: 'same-origin',
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
        }));
    }
    async function workspace(action, params) {
        if (busy) return;
        busy = true; clearError();
        try {
            const response = await post('/chat/workspace', {action, ...params});
            state = {...state, ...response};
            if (response.projects) state.projects = response.projects;
            status.textContent = '';
            render();
        } catch (e) {
            showError(e.message || 'Workspace unavailable.');
        } finally { busy = false; renderControls(); }
    }
    function renderControls() {
        send.disabled = busy || !state.conversation;
        message.disabled = busy || !state.conversation;
    }
    document.getElementById('dc-new-project').addEventListener('click', () => {
        const title = prompt('Project name');
        if (title && title.trim()) workspace('create_project', {title: title.trim()});
    });
    document.getElementById('dc-new-conversation').addEventListener('click', () => {
        if (!state.project) { showError('Select a project first.'); return; }
        const title = prompt('Conversation name');
        if (title && title.trim()) workspace('create_conversation', {
            project_id: state.project, title: title.trim()
        });
    });
    async function awaitReply(id, expectedProject, expectedConversation) {
        const until = Date.now() + 85000;
        let lastState = '';
        while (Date.now() < until) {
            await sleep(800);
            const result = await readJson(await fetch('/chat/status?id=' + encodeURIComponent(id),
                {credentials: 'same-origin', cache: 'no-store'}));
            if (result.state !== lastState) {
                lastState = result.state;
                status.textContent = result.state === 'queued' ? 'Queued for Digit…'
                    : result.state === 'processing' ? 'Digit is processing…' : '';
            }
            if (result.state !== 'complete') continue;
            // Never display the response in another selected conversation.
            if (Number(state.project) === expectedProject &&
                Number(state.conversation) === expectedConversation) {
                addMessage(result.status === 'ok' ? 'Digit' : 'Delivery error',
                    result.reply || 'Digit did not provide a reply.', result.status !== 'ok');
            }
            status.textContent = '';
            return;
        }
        throw new Error(lastState === 'queued' ?
            'Digit did not collect the message.' : 'Digit did not reply before the deadline.');
    }
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy || !state.conversation || !form.reportValidity()) return;
        const sent = message.value;
        const project = Number(state.project);
        const conversation = Number(state.conversation);
        busy = true; renderControls(); clearError(); status.textContent = 'Sending…';
        try {
            const result = await post('/chat', {
                project_id: project, conversation_id: conversation, message: sent
            });
            addMessage('You', sent);
            message.value = '';
            await awaitReply(result.id, project, conversation);
        } catch (e) {
            showError(e.message || 'Message delivery failed.');
        } finally {
            busy = false; status.textContent = ''; renderControls();
        }
    });
    message.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
            e.preventDefault(); form.requestSubmit();
        }
    });
    render();
})();
</script>
<?php endif; ?>
<?php
if (!theme::render('foot', get_defined_vars())) {
    require APPROOT . '/views/inc/foot.php';
}
