<?php
require APPROOT . '/views/inc/head.php';
$state = (string) ($data['state'] ?? 'invalid');
?>
<div class="container my-3">
    <p><a href="/admin">Admin</a> &gt;&gt; Database Lifecycle Test</p>
    <h1>Database Lifecycle Qualification</h1>
    <dl>
        <dt>Schema target</dt><dd><?= htmlspecialchars((string) ($data['schema_version'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
        <dt>Database state</dt><dd><strong><?= htmlspecialchars($state, ENT_QUOTES, 'UTF-8') ?></strong></dd>
        <dt>Reference record count</dt><dd><?= (int) ($data['record_count'] ?? 0) ?></dd>
        <dt>Reference row present</dt><dd><?= !empty($data['reference_found']) ? 'YES' : 'NO' ?></dd>
        <dt>1.0.1 migration column present</dt><dd><?= !empty($data['has_migration_column']) ? 'YES' : 'NO' ?></dd>
    </dl>
    <?php if (!empty($data['message'])): ?>
        <p role="status"><?= htmlspecialchars((string) $data['message'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if (!empty($data['error'])): ?>
        <p role="alert"><?= htmlspecialchars((string) $data['error'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($state === 'missing' || $state === 'update'): ?>
        <form method="POST" action="/admin/db_lifecycle_test">
            <?= $this->csrf_field() ?>
            <input type="hidden" name="action" value="<?= $state === 'missing' ? 'install_sql' : 'update_sql' ?>">
            <button type="submit"><?= $state === 'missing' ? 'Install SQL' : 'Update SQL' ?></button>
        </form>
    <?php elseif ($state === 'current'): ?>
        <div style="display:flex; gap:.75rem; flex-wrap:wrap">
            <form method="POST" action="/admin/db_lifecycle_test" onsubmit="return confirm('Delete the disposable module records?')">
                <?= $this->csrf_field() ?>
                <input type="hidden" name="action" value="delete_data">
                <button type="submit">Delete Data</button>
            </form>
            <form method="POST" action="/admin/db_lifecycle_test">
                <?= $this->csrf_field() ?>
                <input type="hidden" name="action" value="reset_data">
                <button type="submit">Data Reset</button>
            </form>
        </div>
    <?php else: ?>
        <p role="alert">Database state is unsupported. No modification is available.</p>
    <?php endif; ?>
</div>
<?php require APPROOT . '/views/inc/foot.php'; ?>
