<?php
/* [AI:GPT-5.6 Sol | 2026-09-04 14:04:16 UTC] */
// Use the shared includes so administration inherits the active site theme.
$this->csrf_token();
require APPROOT . '/views/inc/head.php';
/* [End AI:GPT-5.6 Sol] */
?>

<p><small><a href="/admin">Admin</a> &gt;&gt; <strong>Themes</strong></small></p>
<div class="container py-5">
    <div class="mb-5">
        <h2 class="fw-bold">Themes</h2>
        <p class="text-muted small text-uppercase">Site Appearance</p>
    </div>
    <?php foreach (['message' => 'success', 'error' => 'danger'] as $key => $style): ?>
        <?php if (!empty($data[$key])): ?>
            <div class="alert alert-<?= $style; ?>"><?= htmlspecialchars((string) $data[$key], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
    <?php endforeach; ?>

    <h5 class="mb-3 text-uppercase text-muted">Core</h5>
    <div class="row g-4 mb-5">
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border"><div class="card-body d-flex flex-column">
                <h5 class="fw-bold">Chaos MVC</h5>
                <p class="small text-muted">Built-in Core Theme</p>
                <div class="mt-auto">
                    <form action="/admin/themes" method="POST">
                        <?= $this->csrf_field(); ?>
                        <input type="hidden" name="theme" value="">
                        <button type="submit" class="btn btn-sm btn-outline-primary w-100 mb-2"
                            <?= ($data['active_theme'] ?? '') === '' ? 'disabled' : ''; ?>>
                            <?= ($data['active_theme'] ?? '') === '' ? 'Active Theme' : 'Apply Theme'; ?>
                        </button>
                    </form>
                    <button class="btn btn-sm btn-light w-100" disabled>Managed by System</button>
                </div>
            </div></div>
        </div>
    </div>
    <hr class="my-5">
    <h5 class="mb-3 text-uppercase text-muted">Addons</h5>
    <div class="row g-4">
        <?php foreach (($data['themes'] ?? []) as $installed): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border theme-card" data-theme="<?= htmlspecialchars((string) $installed['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="card-body d-flex flex-column">
                        <h5 class="fw-bold"><?= htmlspecialchars((string) $installed['name'], ENT_QUOTES, 'UTF-8'); ?></h5>
                        <p><small>Version: <strong class="theme-version"><?= htmlspecialchars((string) $installed['version'], ENT_QUOTES, 'UTF-8'); ?></strong></small></p>
                        <p><small><strong>Description:</strong> <?= htmlspecialchars((string) $installed['description'], ENT_QUOTES, 'UTF-8'); ?></small></p>
                        <p><small><strong>Author:</strong> <?= htmlspecialchars((string) $installed['author'], ENT_QUOTES, 'UTF-8'); ?></small></p>
                        <p><small><strong>Domain:</strong> <?= htmlspecialchars((string) ($installed['domain'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small></p>
                        <p><small><strong>Certified:</strong> <?= htmlspecialchars((string) ($installed['certified'] ?? 'No'), ENT_QUOTES, 'UTF-8'); ?></small></p>
                        <?php if (!empty($installed['metadata_error'])): ?>
                            <p class="text-danger" role="alert">Cannot read theme.json: <?= htmlspecialchars((string) $installed['metadata_error'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php elseif (!empty($installed['update_source_error'])): ?>
                            <p class="text-danger" role="alert">Invalid update_url: <?= htmlspecialchars((string) $installed['update_source_error'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                        <div class="mt-auto">
                            <form action="/admin/themes" method="POST">
                                <?= $this->csrf_field(); ?>
                                <input type="hidden" name="theme" value="<?= htmlspecialchars((string) $installed['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary w-100 mb-2"
                                    <?= ($data['active_theme'] ?? '') === $installed['slug'] ? 'disabled' : ''; ?>>
                                    <?= ($data['active_theme'] ?? '') === $installed['slug'] ? 'Active Theme' : 'Apply Theme'; ?>
                                </button>
                            </form>
                            <?php if (!empty($data['can_update'])): ?>
                                <?php if (!empty($installed['has_update_source'])): ?>
                                    <button type="button" class="btn btn-sm btn-secondary w-100 mb-2 theme-update" data-action="check_update">Checking for updates...</button>
                                <?php elseif (empty($installed['metadata_error']) && empty($installed['update_source_error'])): ?>
                                    <button class="btn btn-sm btn-outline-secondary w-100 mb-2" disabled>Local Theme</button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-outline-warning w-100 mb-2 theme-rollback"
                                    <?= empty($installed['rollback_available']) ? 'disabled' : ''; ?>>Restore previous files</button>
                                <p class="small theme-result" role="status" aria-live="polite"></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
/* [AI:GPT-5.6 Sol | 2026-09-04 14:04:16 UTC] */
const themeUpdateCsrf = <?= json_encode($this->csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
async function themeMaintenance(card, action) {
    const update = card.querySelector('.theme-update');
    const rollback = card.querySelector('.theme-rollback');
    const status = card.querySelector('.theme-result');
    const hadRollback = rollback && !rollback.disabled;
    if (update) update.disabled = true;
    if (rollback) rollback.disabled = true;
    status.textContent = action === 'check_update' ? 'Checking for updates...' : 'Working...';
    try {
        const response = await fetch('/admin/themes', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({action, theme: card.dataset.theme, csrf_token: themeUpdateCsrf, confirm_rollback: action === 'rollback' ? '1' : '0'})
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.error || 'Theme operation failed.');
        if (action === 'check_update') {
            update.dataset.action = data.update_available ? 'update' : 'check_update';
            update.textContent = data.update_available ? 'Update available → ' + data.available_version : 'Up to date — Check again';
            update.classList.toggle('btn-success', data.update_available);
            update.classList.toggle('btn-secondary', !data.update_available);
            status.textContent = data.update_available ? 'New version available.' : 'Up to date.';
            if (rollback) rollback.disabled = !hadRollback;
        } else {
            status.textContent = data.message || 'Completed.';
            const version = card.querySelector('.theme-version');
            if (version) version.textContent = data.version;
            if (rollback) rollback.disabled = action === 'rollback';
            if (update) {
                update.dataset.action = 'check_update';
                update.textContent = 'Check for updates';
            }
        }
    } catch (error) {
        status.textContent = error instanceof Error ? error.message : 'Theme operation failed.';
        if (update) update.textContent = action === 'update' ? 'Update failed — Retry' : 'Check failed — Retry';
        if (rollback) rollback.disabled = !hadRollback;
    } finally {
        if (update) update.disabled = false;
    }
}
document.querySelectorAll('.theme-card').forEach(card => {
    const update = card.querySelector('.theme-update');
    const rollback = card.querySelector('.theme-rollback');
    if (update) {
        update.addEventListener('click', () => themeMaintenance(card, update.dataset.action));
        themeMaintenance(card, 'check_update');
    }
    if (rollback) rollback.addEventListener('click', () => {
        if (confirm('Replace this theme with its previous files?')) themeMaintenance(card, 'rollback');
    });
});
/* [End AI:GPT-5.6 Sol] */
</script>
<?php
/* [AI:GPT-5.6 Sol | 2026-09-04 14:04:16 UTC] */
require APPROOT . '/views/inc/foot.php';
/* [End AI:GPT-5.6 Sol] */
?>
