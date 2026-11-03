<?php
// Access was checked by the controller before rendering.
if (!theme::render('head', get_defined_vars())) {
    require APPROOT . '/views/inc/head.php';
}
$enabled = !empty($data['enabled']);
$reply = $data['reply'] ?? null;
$error = $data['error'] ?? null;
$submitted = (string) ($data['submitted'] ?? '');
?>
<div class="container my-3" id="digit-chat">
    <h1>Digit Chat</h1>
    <?php if (!$enabled): ?>
        <p role="status">Chat is not yet available.</p>
    <?php else: ?>
        <div id="chat-history" role="log" aria-live="polite" aria-label="Chat messages">
            <?php if (is_string($reply)): ?>
                <section class="mb-3">
                    <strong>You</strong>
                    <p style="white-space:pre-wrap"><?= htmlspecialchars($submitted, ENT_QUOTES, 'UTF-8') ?></p>
                    <strong>Digit</strong>
                    <p style="white-space:pre-wrap"><?= htmlspecialchars($reply, ENT_QUOTES, 'UTF-8') ?></p>
                </section>
            <?php endif; ?>
        </div>
        <p id="chat-error" role="alert" <?= is_string($error) ? '' : 'hidden' ?>><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></p>
        <p id="chat-status" role="status" aria-live="polite"></p>
        <form action="/chat" method="POST" id="chat-form">
            <?= $this->csrf_field() ?>
            <label for="chat-message">Message</label>
            <textarea id="chat-message" name="message" maxlength="2047" rows="3" required><?= htmlspecialchars($submitted, ENT_QUOTES, 'UTF-8') ?></textarea>
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

            function addMessage(who, content) {
                const section = document.createElement('section');
                section.className = 'mb-3';
                const heading = document.createElement('strong');
                heading.textContent = who;
                const body = document.createElement('p');
                body.style.whiteSpace = 'pre-wrap';
                body.textContent = content;
                section.append(heading, body);
                history.appendChild(section);
                section.scrollIntoView({block: 'nearest'});
            }

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (send.disabled || !form.reportValidity()) return;
                const submitted = message.value;
                error.hidden = true;
                error.textContent = '';
                status.textContent = 'Sending…';
                send.disabled = true;
                try {
                    const response = await fetch(form.getAttribute('action'), {
                        method: 'POST',
                        body: new FormData(form),
                        headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                        credentials: 'same-origin'
                    });
                    const type = response.headers.get('content-type') || '';
                    if (!type.includes('application/json')) {
                        throw new Error('Unexpected server response (HTTP ' + response.status + ').');
                    }
                    const result = await response.json();
                    if (!response.ok || !result.ok) {
                        throw new Error(result.error || 'Request failed (HTTP ' + response.status + ').');
                    }
                    addMessage('You', submitted);
                    addMessage('Digit', String(result.reply ?? ''));
                    message.value = '';
                    status.textContent = '';
                    message.focus();
                } catch (failure) {
                    error.textContent = failure instanceof Error ? failure.message : 'Unable to send message.';
                    error.hidden = false;
                    status.textContent = '';
                } finally {
                    send.disabled = false;
                }
            });
        })();
        </script>
    <?php endif; ?>
</div>
<?php
if (!theme::render('foot', get_defined_vars())) {
    require APPROOT . '/views/inc/foot.php';
}
