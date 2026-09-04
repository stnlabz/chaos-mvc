<?php
/* [AI:GPT-5.6 Sol | 2026-09-04 14:04:16 UTC] */
// Recovery must remain usable when the active theme contains broken PHP.
$this->csrf_token();
require APPROOT . '/views/inc/classic_head.php';
/* [End AI:GPT-5.6 Sol] */
?>

<div class="container py-5">
    <h1 class="mb-4">Themes</h1>

    <?php if (!empty($data['message'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars((string) $data['message'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($data['error'])): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars((string) $data['error'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form action="/admin/themes" method="POST">
        <?= $this->csrf_field(); ?>

        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <label class="card h-100 p-4">
                    <input type="radio" name="theme" value=""
                        <?= ($data['active_theme'] ?? '') === '' ? 'checked' : ''; ?>>
                    <strong class="mt-3">Chaos MVC</strong>
                    <span class="text-muted">Built-in Core theme</span>
                </label>
            </div>

            <?php foreach (($data['themes'] ?? []) as $installed): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 p-4 theme-card" data-theme="<?= htmlspecialchars((string) $installed['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                    <label>
                        <input type="radio" name="theme"
                            value="<?= htmlspecialchars((string) $installed['slug'], ENT_QUOTES, 'UTF-8'); ?>"
                            <?= ($data['active_theme'] ?? '') === $installed['slug'] ? 'checked' : ''; ?>>
                        <strong class="mt-3">
                            <?= htmlspecialchars((string) $installed['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </strong>
                        <?php if ($installed['version'] !== ''): ?>
                            <span>Version <span class="theme-version"><?= htmlspecialchars((string) $installed['version'], ENT_QUOTES, 'UTF-8'); ?></span></span>
                        <?php endif; ?>
                        <?php if ($installed['author'] !== ''): ?>
                            <span>By <?= htmlspecialchars((string) $installed['author'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <?php if ($installed['description'] !== ''): ?>
                            <p class="text-muted mt-2 mb-0">
                                <?= htmlspecialchars((string) $installed['description'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        <?php endif; ?>
                    </label>
                    <!-- [AI:GPT-5.6 Sol | 2026-09-04 14:04:16 UTC] -->
                    <?php if (!empty($data['can_update'])): ?>
                        <?php if (!empty($installed['has_update_source'])): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-3 theme-update" data-action="check_update">Check for updates</button>
                        <?php else: ?>
                            <span class="text-muted small mt-3">Local Theme — no HTTPS update source</span>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-outline-warning mt-2 theme-rollback"
                            <?= empty($installed['rollback_available']) ? 'disabled' : ''; ?>>Restore previous files</button>
                        <p class="small mt-2 theme-result" role="status" aria-live="polite"></p>
                    <?php endif; ?>
                    <!-- [End AI:GPT-5.6 Sol] -->
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <button class="btn btn-primary mt-4" type="submit">Apply Theme</button>
    </form>
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
require APPROOT . '/views/inc/classic_foot.php';
/* [End AI:GPT-5.6 Sol] */
?>
