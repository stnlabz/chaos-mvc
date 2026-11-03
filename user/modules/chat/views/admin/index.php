<?php
if (!theme::render('head', get_defined_vars())) {
    require APPROOT . '/views/inc/head.php';
}
?>
<main class="container my-4">
    <h1>Digit Chat — Administration</h1>
    <p>Module: Chat | Version: 0.4.0 | Schema Version: 1.1.0</p>
    <p>Database State:
      <strong><?= htmlspecialchars((string) ($data['database_state'] ?? 'unknown'), ENT_QUOTES, 'UTF-8') ?></strong>
    </p>
    <?php if (!empty($data['error'])): ?>
      <p role="alert"><?= htmlspecialchars((string) $data['error'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if (($data['status'] ?? '') === 'installed'): ?>
      <p>Chat schema installed.</p>
    <?php elseif (($data['status'] ?? '') === 'updated'): ?>
      <p>Chat schema updated.</p>
    <?php endif; ?>
    <?php if (($data['database_state'] ?? '') === 'missing'): ?>
      <p>Chat requires explicit schema installation before use.</p>
      <form method="POST" action="/admin/chat">
        <?= $this->csrf_field() ?>
        <input type="hidden" name="action" value="install_sql">
        <button type="submit">Install SQL</button>
      </form>
    <?php elseif (($data['database_state'] ?? '') === 'update'): ?>
      <p>A supported Chat schema update is pending. Current chat data is preserved.</p>
      <form method="POST" action="/admin/chat">
        <?= $this->csrf_field() ?>
        <input type="hidden" name="action" value="update_sql">
        <button type="submit">Update SQL</button>
      </form>
    <?php elseif (($data['database_state'] ?? '') === 'current'): ?>
      <p>Chat database is current. <a href="/chat">Open Digit Chat</a></p>
    <?php else: ?>
      <p role="alert">The Chat schema is incomplete or unsupported. No automatic installation or migration will run.</p>
    <?php endif; ?>
</main>
<?php
if (!theme::render('foot', get_defined_vars())) {
    require APPROOT . '/views/inc/foot.php';
}
