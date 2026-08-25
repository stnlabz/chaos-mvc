<?php /* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */ ?>
<?php require APPROOT . '/views/inc/head.php'; ?>
<div class="container py-5">
    <div class="mb-4">
        <a href="/admin" class="small text-decoration-none">&larr; Admin</a>
        <h1 class="fw-bold mt-2">Chaos MVC Core Updater</h1>
        <p class="text-muted">
            Core updates are authenticated independently from module updates.
        </p>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <span class="text-muted small text-uppercase">Installed Core</span>
                    <div class="fs-4 fw-bold">
                        <?= htmlspecialchars($installed_version, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </div>
                <form action="/admin/core_updates/check" method="POST">
                    <input type="hidden" name="csrf_token"
                           value="<?= htmlspecialchars($this->csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn btn-primary">Check for Core update</button>
                </form>
            </div>

            <?php if (is_array($result)): ?>
                <?php $successful = !empty($result['success']); ?>
                <div class="alert <?= $successful ? 'alert-success' : 'alert-warning' ?> mt-4 mb-0">
                    <strong><?= htmlspecialchars((string) ($result['outcome'] ?? 'unknown'), ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php if (!empty($result['target_version'])): ?>
                        <div>
                            Available version:
                            <?= htmlspecialchars((string) $result['target_version'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($result['message'])): ?>
                        <div><?= htmlspecialchars((string) $result['message'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <?php if (!empty($result['error_code'])): ?>
                        <small>Error code: <?= htmlspecialchars((string) $result['error_code'], ENT_QUOTES, 'UTF-8') ?></small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (is_array($offer)): ?>
                <form action="/admin/core_updates/validate_package" method="POST" class="mt-3">
                    <input type="hidden" name="csrf_token"
                           value="<?= htmlspecialchars($this->csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn btn-outline-primary">
                        Validate package for
                        <?= htmlspecialchars((string) ($offer['version'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </form>
            <?php endif; ?>

            <?php if (is_array($stage)): ?>
                <div class="border rounded p-3 mt-3">
                    <strong>Validated Core package</strong>
                    <div class="small text-muted mb-3">
                        Target version:
                        <?= htmlspecialchars((string) ($stage['target_version'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <form action="/admin/core_updates/install" method="POST">
                        <input type="hidden" name="csrf_token"
                               value="<?= htmlspecialchars($this->csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn btn-danger">
                            Update Chaos MVC to
                            <?= htmlspecialchars((string) ($stage['target_version'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (($maintenance['active'] ?? false) === true || is_array($rollback)): ?>
        <div class="card border-warning mt-4">
            <div class="card-body p-4">
                <h2 class="h5">Core recovery</h2>
                <?php if (($maintenance['active'] ?? false) === true): ?>
                    <p class="text-warning-emphasis">
                        Maintenance is active during phase
                        <strong><?= htmlspecialchars((string) ($maintenance['phase'] ?? 'unknown'), ENT_QUOTES, 'UTF-8') ?></strong>.
                    </p>
                <?php endif; ?>
                <?php if (is_array($rollback)): ?>
                    <p class="small text-muted">
                        Previous Core recovery material:
                        <?= htmlspecialchars((string) ($rollback['version'] ?? 'unknown'), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                <?php endif; ?>
                <form action="/admin/core_updates/recover" method="POST">
                    <input type="hidden" name="csrf_token"
                           value="<?= htmlspecialchars($this->csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn btn-outline-warning">
                        Restore previous Core
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <p class="text-muted small mt-3 mb-0">
        Core installation replaces all signed-manifest Core files. `/public` and local configuration remain protected.
    </p>
</div>
<?php require APPROOT . '/views/inc/foot.php'; ?>
<?php /* [End AI:GPT-5.6 Sol] */ ?>
