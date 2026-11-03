<?php
if (!theme::render('head', get_defined_vars())) {
    require APPROOT . '/views/inc/head.php';
}
?>
<style>
#digit-chat{max-width:880px;margin:1.5rem auto;padding:0 1rem}
#digit-chat .dc-shell{border:1px solid #344153;border-radius:16px;overflow:hidden;background:#111a26;color:#e9eff7;box-shadow:0 12px 34px rgba(0,0,0,.16)}
#digit-chat .dc-head{display:flex;align-items:center;gap:12px;padding:20px 24px;border-bottom:1px solid #344153}
#digit-chat .dc-indicator{width:11px;height:11px;border-radius:50%;background:#67c49b;flex-shrink:0}
#digit-chat h1{font-size:1.35rem;margin:0;color:#f4f7fb}
#digit-chat .dc-sub{color:#a8b8ca;font-size:.85rem;margin:3px 0 0}
#digit-chat .dc-history{height:min(58vh,530px);min-height:280px;overflow-y:auto;padding:22px;display:flex;flex-direction:column;gap:16px}
#digit-chat .dc-empty{margin:auto;text-align:center;color:#9fb1c5;max-width:26rem;line-height:1.6}
#digit-chat .dc-entry{display:flex;flex-direction:column;gap:5px;max-width:85%}
#digit-chat .dc-entry[data-who="You"]{align-self:flex-end;align-items:flex-end}
#digit-chat .dc-entry[data-who="Digit"]{align-self:flex-start}
#digit-chat .dc-who{font-size:.75rem;color:#a8b8ca;letter-spacing:.02em}
#digit-chat .dc-bubble{white-space:pre-wrap;overflow-wrap:anywhere;margin:0;padding:12px 16px;border-radius:14px;background:#243246;line-height:1.55}
#digit-chat .dc-entry[data-who="You"] .dc-bubble{background:#285d79;color:#fff}
#digit-chat .dc-entry[data-error="true"] .dc-bubble{background:#422b34;color:#ffd9d9}
#digit-chat .dc-footer{padding:16px 20px 20px;border-top:1px solid #344153}
#digit-chat .dc-state{min-height:22px;color:#a8b8ca;font-size:.82rem;margin:0 0 8px}
#digit-chat .dc-error{color:#ffb9b9;font-size:.9rem;margin:0 0 10px}
#digit-chat .dc-compose{display:flex;align-items:flex-end;gap:10px}
#digit-chat textarea{flex:1;resize:vertical;min-height:65px;max-height:180px;padding:12px 14px;border:1px solid #64748b;border-radius:10px;background:#1c2939;color:#f5f7fa;font:inherit}
#digit-chat textarea:focus{outline:2px solid #7bbde4;outline-offset:1px}
#digit-chat button{border:0;border-radius:10px;padding:13px 20px;background:#80bce1;color:#122638;font-weight:700;cursor:pointer}
#digit-chat button:disabled{opacity:.6;cursor:wait}
@media(max-width:600px){#digit-chat .dc-head{padding:16px}#digit-chat .dc-history{padding:14px;min-height:330px}#digit-chat .dc-footer{padding:12px}#digit-chat .dc-entry{max-width:95%}#digit-chat button{padding:13px}}
</style>
<section id="digit-chat" aria-label="Digit Chat">
  <div class="dc-shell">
    <header class="dc-head">
      <span class="dc-indicator" aria-hidden="true"></span>
      <div>
        <h1>Digit</h1>
        <p class="dc-sub">Chat interface</p>
      </div>
    </header>
    <div class="dc-history" id="chat-history" role="log" aria-live="polite" aria-label="Conversation">
      <p class="dc-empty" id="chat-empty">Send Digit a message to begin.</p>
    </div>
    <div class="dc-footer">
      <p id="chat-status" class="dc-state" role="status" aria-live="polite"></p>
      <p id="chat-error" class="dc-error" role="alert" <?= empty($data['initial_error']) ? 'hidden' : '' ?>><?= htmlspecialchars((string)($data['initial_error'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
      <form id="chat-form" action="/chat" method="POST">
        <?= $this->csrf_field() ?>
        <label for="chat-message" style="display:block;margin-bottom:8px">Message Digit</label>
        <div class="dc-compose">
          <textarea id="chat-message" name="message" maxlength="2047" rows="2" placeholder="Type your message…" required></textarea>
          <button type="submit" id="chat-send">Send</button>
        </div>
      </form>
    </div>
  </div>
</section>
<script>
(() => {
    const form = document.getElementById('chat-form');
    const history = document.getElementById('chat-history');
    const empty = document.getElementById('chat-empty');
    const message = document.getElementById('chat-message');
    const send = document.getElementById('chat-send');
    const error = document.getElementById('chat-error');
    const status = document.getElementById('chat-status');
    if (!window.fetch || !window.FormData) return;
    const initialId = <?= json_encode($data['initial_id'] ?? null) ?>;
    const initialMessage = <?= json_encode($data['initial_message'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

    function addMessage(who, content, failed = false) {
        empty?.remove();
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
    async function readJson(response) {
        if (!(response.headers.get('content-type') || '').includes('application/json')) {
            throw new Error('Unexpected server response (HTTP ' + response.status + ').');
        }
        const result = await response.json();
        if (!response.ok || result.ok === false) {
            throw new Error(result.error || 'Request failed (HTTP ' + response.status + ').');
        }
        return result;
    }
    async function awaitReply(id) {
        const until = Date.now() + 85000;
        let lastState = '';
        while (Date.now() < until) {
            await sleep(800);
            const state = await readJson(await fetch(
                '/chat/status?id=' + encodeURIComponent(id),
                {credentials: 'same-origin', cache: 'no-store'}
            ));
            if (state.state !== lastState) {
                lastState = state.state;
                status.textContent = state.state === 'processing'
                    ? 'Digit received the message. Waiting for a reply…'
                    : 'Message queued for Digit…';
            }
            if (state.state !== 'complete') continue;
            status.textContent = '';
            if (state.status === 'ok' && typeof state.reply === 'string' && state.reply.length) {
                addMessage('Digit', state.reply);
            } else {
                addMessage('Delivery error', String(state.reply || 'Digit did not produce a usable response.'), true);
            }
            message.focus();
            return;
        }
        throw new Error(lastState === 'queued'
            ? 'Digit has not collected this message.'
            : 'No completed reply was delivered before the deadline.');
    }
    async function submit(event) {
        event.preventDefault();
        if (send.disabled || !form.reportValidity()) return;
        const submitted = message.value;
        error.hidden = true;
        error.textContent = '';
        send.disabled = true;
        status.textContent = 'Sending…';
        try {
            const queued = await readJson(await fetch(form.getAttribute('action'), {
                method: 'POST',
                body: new FormData(form),
                headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
                credentials: 'same-origin'
            }));
            if (!/^[a-f0-9]{32}$/.test(queued.id || '')) throw new Error('Invalid delivery receipt.');
            addMessage('You', submitted);
            message.value = '';
            await awaitReply(queued.id);
        } catch (failure) {
            error.textContent = failure instanceof Error ? failure.message : 'Message delivery failed.';
            error.hidden = false;
            status.textContent = '';
        } finally {
            send.disabled = false;
        }
    }
    form.addEventListener('submit', submit);
    message.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            form.requestSubmit();
        }
    });
    if (initialId && /^[a-f0-9]{32}$/.test(initialId)) {
        addMessage('You', initialMessage);
        send.disabled = true;
        awaitReply(initialId).catch(failure => {
            error.textContent = failure.message || 'Unable to retrieve the reply.';
            error.hidden = false;
            status.textContent = '';
        }).finally(() => { send.disabled = false; });
    }
})();
</script>
<?php
if (!theme::render('foot', get_defined_vars())) {
    require APPROOT . '/views/inc/foot.php';
}
