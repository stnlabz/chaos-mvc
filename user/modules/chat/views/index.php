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
<div class="container my-3">
    <h1>Digit Chat</h1>
    <?php if (!$enabled): ?>
        <p role="status">Chat is not yet available.</p>
    <?php else: ?>
        <?php if (is_string($error)): ?>
            <p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if (is_string($reply)): ?>
            <section aria-label="Last exchange">
                <h2>You</h2>
                <p style="white-space:pre-wrap"><?= htmlspecialchars($submitted, ENT_QUOTES, 'UTF-8') ?></p>
                <h2>Digit</h2>
                <p style="white-space:pre-wrap"><?= htmlspecialchars($reply, ENT_QUOTES, 'UTF-8') ?></p>
            </section>
        <?php endif; ?>
        <form action="/chat" method="POST">
            <?= $this->csrf_field() ?>
            <label for="chat-message">Message</label>
            <textarea id="chat-message" name="message" maxlength="2047" rows="5" required><?= htmlspecialchars($submitted, ENT_QUOTES, 'UTF-8') ?></textarea>
            <button type="submit">Send</button>
        </form>
    <?php endif; ?>
</div>
<?php
if (!theme::render('foot', get_defined_vars())) {
    require APPROOT . '/views/inc/foot.php';
}
