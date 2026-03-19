<?php require APPROOT . '/views/inc/head.php'; ?>

<p><small><a href="/admin">Admin</a> >> <strong>Modules</strong></small></p>

<div class="container py-5">

    <div class="mb-5">
        <h2 class="fw-bold">Modules</h2>
        <p class="text-muted small text-uppercase">System Components</p>
    </div>

    <?php
    $core = [];
    $addons = [];

    foreach ($data['modules'] as $module) {
        if ($module['is_core']) {
            $core[] = $module;
        } else {
            $addons[] = $module;
        }
    }
    require_once APPROOT . '/core/version.php';
    $systemVersion = defined('CHAOS_VERSION') ? CHAOS_VERSION : '0.0.0';
    ?>

    <!-- CORE -->
    <h5 class="mb-3 text-uppercase text-muted">Core</h5>
    <div class="row g-4 mb-5">
        <?php foreach ($core as $module): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border">
                <div class="card-body d-flex flex-column">

                    <h5 class="fw-bold"><?= htmlspecialchars($module['slug']); ?></h5>
                    <p class="small text-muted">Core Module</p>

                    <p><small>Version: <strong><?= $systemVersion; ?></strong></small></p>

                    <div class="mt-auto">
                        <a href="/admin/<?= $module['slug']; ?>" class="btn btn-sm btn-outline-primary w-100 mb-2">
                            Manage
                        </a>

                        <button class="btn btn-sm btn-light w-100" disabled>
                            Managed by System
                        </button>
                    </div>

                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <hr class="my-5">

    <!-- ADDONS -->
    <h5 class="mb-3 text-uppercase text-muted">Addons</h5>
    <div class="row g-4">

        <?php foreach ($addons as $module):

            $configPath = APPROOT . '/data/modules/' . $module['slug'] . '.json';
            $version = '0.0.0';
            $hasUpdate = false;

            if (file_exists($configPath)) {
                $config = json_decode(file_get_contents($configPath), true);
                $version = $config['version'] ?? '0.0.0';

                if (!empty($config['update_url'])) {
                    $remote = @file_get_contents($config['update_url']);
                    if ($remote) {
                        $remote = json_decode($remote, true);
                        $remoteVersion = $remote['version'] ?? $version;

                        if (version_compare($remoteVersion, $version, '>')) {
                            $hasUpdate = true;
                        }
                    }
                }
            }
        ?>

        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border">
                <div class="card-body d-flex flex-column">

                    <h5 class="fw-bold"><?= htmlspecialchars($module['slug']); ?></h5>
                    <p class="small text-muted">Addon Module</p>

                    <p><small>Version: <strong><?= $version; ?></strong></small></p>

                    <div class="mt-auto">

                        <a href="/admin/<?= $module['slug']; ?>" class="btn btn-sm btn-outline-primary w-100 mb-2">
                            Manage
                        </a>

                        <?php if ($hasUpdate): ?>
                            <button class="btn btn-sm btn-success w-100 mb-2 btn-update"
                                data-module="<?= htmlspecialchars($module['slug']); ?>">
                                Update
                            </button>
                        <?php else: ?>
                            <button class="btn btn-sm btn-secondary w-100 mb-2" disabled>
                                Up to Date
                            </button>
                        <?php endif; ?>

                        <form action="/admin/uninstall" method="POST"
                              onsubmit="return confirm('EXTREME DANGER: This will permanently remove all data and files for this module.');">
                            <input type="hidden" name="module" value="<?= htmlspecialchars($module['slug']); ?>">
                            <button type="submit" class="btn btn-sm btn-danger w-100">
                                Nuke
                            </button>
                        </form>

                    </div>

                </div>
            </div>
        </div>

        <?php endforeach; ?>

    </div>

</div>

<script>
document.querySelectorAll('.btn-update').forEach(btn => {
    btn.addEventListener('click', async () => {

        const module = btn.dataset.module;

        // 🔄 visual feedback
        btn.innerHTML = 'Updating...';
        btn.disabled = true;

        try {
            const res = await fetch(`/admin/update?module=${module}`);
            const data = await res.json();

            if (data.success) {
                btn.innerHTML = `Updated → ${data.version}`;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-secondary');

                // update version text without reload
                const card = btn.closest('.card');
                const versionEl = card.querySelector('strong');

                if (versionEl) {
                    versionEl.innerText = data.version;
                }

            } else {
                btn.innerHTML = 'Failed';
                btn.disabled = false;
            }

        } catch (e) {
            btn.innerHTML = 'Error';
            btn.disabled = false;
        }

    });
});
</script>

<?php require APPROOT . '/views/inc/foot.php'; ?>
