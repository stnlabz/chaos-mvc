<?php
if (!theme::render('head', get_defined_vars())) {
    require APPROOT . '/views/inc/head.php';
}
?>
<div class="container my-3" id="digit-chat">
    <h1>Digit Chat</h1>
    <div id="chat-history" role="log" aria-live="polite" aria-label="Chat messages"></div>
    <p id="chat-error" role="alert" hidden></p>
    <p id="chat-status" role="status" aria-live="polite"></p>
    <form action="/chat" method="POST" id="chat-form">
        <?= $this->csrf_field() ?>
        <label for="chat-message">Message</label>
        <textarea id="chat-message" name="message" maxlength="2047" rows="3" required></textarea>
        <button type="submit" id="chat-send">Send</button>
    </form>
    <script>
    (() => {
        const form = document.getElementById('chat-form');
        const history = document.getElementById('chat-history');
        const message = document.getElementById('chat-message');
        const send = document.getElementById('chat-send');
        const error = document.getElementById('chat-error');
        const status = document.getElementById('chat-status');
        if (!window.fetch || !window.FormData) return;

        const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
        function addMessage(who, content) {
            const section = document.createElement('section');
            section.className = 'mb-3';
            const label = document.createElement('strong');
            label.textContent = who;
            const body = document.createElement('p');
            body.style.whiteSpace = 'pre-wrap';
            body.textContent = content;
            section.append(label, body);
            history.appendChild(section);
            section.scrollIntoView({block: 'nearest'});
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
        form.addEventListener('submit', async event => {
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
                    headers: {'Accept': 'application/json'},
                    credentials: 'same-origin'
                }));
                if (!/^[a-f0-9]{32}$/.test(queued.id || '')) {
                    throw new Error('Invalid delivery receipt.');
                }
                addMessage('You', submitted);
                message.value = '';
                status.textContent = 'Waiting for Digit…';
                const until = Date.now() + 85000;
                let delivered = false;
                while (Date.now() < until) {
                    await sleep(750);
                    const state = await readJson(await fetch(
                        '/chat/status?id=' + encodeURIComponent(queued.id),
                        {credentials: 'same-origin', cache: 'no-store'}
                    ));
                    if (state.state !== 'complete') continue;
                    if (state.status === 'ok') {
                        addMessage('Digit', String(state.reply ?? ''));
                    } else {
                        addMessage('Digit', String(state.reply || 'Digit could not complete the request.'));
                    }
                    delivered = true;
                    break;
                }
                if (!delivered) throw new Error('Digit did not respond before the request expired.');
                status.textContent = '';
                message.focus();
            } catch (failure) {
                error.textContent = failure instanceof Error ? failure.message : 'Unable to deliver message.';
                error.hidden = false;
                status.textContent = '';
            } finally {
                send.disabled = false;
            }
        });
    })();
    </script>
</div>
<?php
if (!theme::render('foot', get_defined_vars())) {
    require APPROOT . '/views/inc/foot.php';
}
