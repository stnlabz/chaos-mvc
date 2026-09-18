<?php
// path: /user/modules/example/views/admin/index.php

require APPROOT . '/views/inc/head.php';

$module = is_array($data['module'] ?? null) ? $data['module'] : [];
$state = (string) ($data['database_state'] ?? 'invalid');
$records = is_array($data['records'] ?? null) ? $data['records'] : [];
$message = $data['message'] ?? null;
$error = $data['error'] ?? null;
?>

<p>
    <small>
        <a href="/admin">Admin</a> &gt;&gt;
        <strong>Example</strong>
    </small>
</p>

<div class="container my-3">
    <h1>Example Module Administration</h1>

    <p>
        This administration interface is the working reference for the
        capabilities a ChAoS MVC user Module may need to implement. It
        demonstrates database state detection, SQL installation, exact
        schema updates, module-owned data deletion, explicit Data Reset,
        complete Create/Read/Update/Delete operations, validation,
        CSRF-protected POST actions, and visible success or failure status.
    </p>

    <p>
        Data lifecycle operations are intentionally distinct from the Module
        lifecycle. <strong>Delete Data</strong> removes Example-owned records
        while preserving the installed schema. <strong>Data Reset</strong>
        removes mutable Example data and restores the canonical reference
        records supplied with the Module.
    </p>

    <?php if ($message): ?>
        <div
            role="status"
            style="padding: .75rem; margin-bottom: 1rem; border: 1px solid #198754;"
        >
            <strong>Success:</strong>
            <?= htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div
            role="alert"
            style="padding: .75rem; margin-bottom: 1rem; border: 1px solid #dc3545;"
        >
            <strong>Error:</strong>
            <?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <section>
        <h2>Module &amp; Data Lifecycle</h2>

        <dl>
            <dt>Module</dt>
            <dd><?= htmlspecialchars((string) ($module['name'] ?? 'Example'), ENT_QUOTES, 'UTF-8'); ?></dd>

            <dt>Module Version</dt>
            <dd><?= htmlspecialchars((string) ($module['version'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>

            <dt>Schema Version</dt>
            <dd><?= htmlspecialchars((string) ($module['schema_version'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>

            <dt>Database State</dt>
            <dd><strong><?= htmlspecialchars($state, ENT_QUOTES, 'UTF-8'); ?></strong></dd>
        </dl>

        <?php if ($state === 'missing'): ?>
            <form method="POST" action="/admin/example">
                <?= $this->csrf_field(); ?>
                <input type="hidden" name="action" value="install_sql">
                <button type="submit">Install SQL</button>
            </form>
        <?php elseif ($state === 'update'): ?>
            <form method="POST" action="/admin/example">
                <?= $this->csrf_field(); ?>
                <input type="hidden" name="action" value="update_sql">
                <button type="submit">Update SQL</button>
            </form>
        <?php elseif ($state === 'invalid'): ?>
            <p>
                The Example database state is invalid. No lifecycle or CRUD
                mutation will be performed until the state is corrected.
            </p>
        <?php endif; ?>

        <?php if ($state === 'current'): ?>
            <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
                <form
                    method="POST"
                    action="/admin/example"
                    onsubmit="return confirm('Delete all Example module data while preserving its schema?');"
                >
                    <?= $this->csrf_field(); ?>
                    <input type="hidden" name="action" value="delete_data">
                    <button type="submit">Delete Data</button>
                </form>

                <form
                    method="POST"
                    action="/admin/example"
                    onsubmit="return confirm('Reset Example data to the canonical reference state?');"
                >
                    <?= $this->csrf_field(); ?>
                    <input type="hidden" name="action" value="reset_data">
                    <button type="submit">Data Reset</button>
                </form>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($state === 'current'): ?>
        <hr>

        <section>
            <h2>Create Example Record</h2>

            <form method="POST" action="/admin/example">
                <?= $this->csrf_field(); ?>
                <input type="hidden" name="action" value="create">

                <p>
                    <label for="example-title">Title</label><br>
                    <input
                        id="example-title"
                        type="text"
                        name="title"
                        maxlength="150"
                        required
                        style="width:100%; box-sizing:border-box;"
                    >
                </p>

                <p>
                    <label for="example-body">Body</label><br>
                    <textarea
                        id="example-body"
                        name="body"
                        rows="5"
                        maxlength="2000"
                        required
                        style="width:100%; box-sizing:border-box;"
                    ></textarea>
                </p>

                <p>
                    <label>
                        <input type="checkbox" name="is_active" value="1" checked>
                        Active
                    </label>
                </p>

                <button type="submit">Create Record</button>
            </form>
        </section>

        <hr>

        <section>
            <h2>Example Records</h2>

            <?php if (empty($records)): ?>
                <p>No Example records exist.</p>
            <?php endif; ?>

            <?php foreach ($records as $record): ?>
                <article style="margin-bottom:2rem;">
                    <form method="POST" action="/admin/example">
                        <?= $this->csrf_field(); ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= (int) $record['id']; ?>">

                        <p>
                            <label>
                                Title<br>
                                <input
                                    type="text"
                                    name="title"
                                    maxlength="150"
                                    required
                                    value="<?= htmlspecialchars(
                                        (string) $record['title'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>"
                                    style="width:100%; box-sizing:border-box;"
                                >
                            </label>
                        </p>

                        <p>
                            <label>
                                Body<br>
                                <textarea
                                    name="body"
                                    rows="5"
                                    maxlength="2000"
                                    required
                                    style="width:100%; box-sizing:border-box;"
                                ><?= htmlspecialchars(
                                    (string) $record['body'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?></textarea>
                            </label>
                        </p>

                        <p>
                            <label>
                                <input
                                    type="checkbox"
                                    name="is_active"
                                    value="1"
                                    <?= (int) $record['is_active'] === 1 ? 'checked' : ''; ?>
                                >
                                Active
                            </label>
                        </p>

                        <button type="submit">Update Record</button>
                    </form>

                    <form
                        method="POST"
                        action="/admin/example"
                        onsubmit="return confirm('Delete this Example record?');"
                        style="margin-top:.5rem;"
                    >
                        <?= $this->csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $record['id']; ?>">
                        <button type="submit">Delete Record</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</div>

<?php

require APPROOT . '/views/inc/foot.php';
