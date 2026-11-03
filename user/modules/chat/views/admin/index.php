<?php
if (!theme::render('head', get_defined_vars())) {
    require APPROOT . '/views/inc/head.php';
}
?>
<main class="container my-4">
    <h1>Digit Chat — Administration</h1>
    <p>Module: Chat | Version: 0.3.0 | Schema: 1.0.0</p>
    <p>Database state: <strong><?= htmlspecialchars((string) ($data['database_state'] ?? 'unknown'), ENT_QUOTES, 'UTF-8') ?></strong></p>
    <?php if (!empty($data['error'])): ?>
        <p role="alert"><?= htmlspecialchars((string) $data['error'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if (!empty($data['installed'])): ?>
        <p>Chat schema installed.</p>
    <?php endif; ?>
    <?php if (($data['database_state'] ?? '') === 'missing'): ?>
        <p>Chat is unavailable until its module-owned MySQL schema is explicitly installed.</p>
        <form method="POST" action="/admin/chat">
            <?= $this->csrf_field() ?>
            <input type="hidden" name="action" value="install_sql">
            <button type="submit">Install SQL</button>
        </form>
    <?php else: ?>
        <p>Projects, conversations, messages and deliveries are available. Opening this page does not modify data.</p>
        <a href="/chat">Open Digit Chat</a>
    <?php endif; ?>
</main>
<?php
if (!theme::render('foot', get_defined_vars())) {
    require APPROOT . '/views/inc/foot.php';
}
