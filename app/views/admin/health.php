<?php require APPROOT . '/views/inc/head.php'; ?>
<p><small><a href="/admin">Admin</a> >> <strong>Health</strong></small></p>
<div class="container py-5">
    <div class="mb-5 text-center">
        <h2 class="fw-bold">System Health</h2>
        <p class="text-muted small text-uppercase">Environment Core Status</p>
    </div>

    <div class="row g-4 justify-content-center">
        
        <div class="col-lg-3 col-md-6">
            <div class="card h-100 border-0 shadow-sm p-3">
                <div class="card-body text-center">
                    <i class="bi bi-cpu h1 text-primary d-block mb-3"></i>
                    <h6 class="fw-bold">Server</h6>
                    <small class="d-block text-muted mb-2"><?= htmlspecialchars((string) $server['software'], ENT_QUOTES, 'UTF-8'); ?></small>
                    <span class="badge bg-primary">PHP <?= htmlspecialchars((string) $server['php_version'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card h-100 border-0 shadow-sm p-3">
                <div class="card-body text-center">
                    <i class="bi bi-hdd-network h1 text-primary d-block mb-3"></i>
                    <h6 class="fw-bold">Storage</h6>
                    <small class="d-block text-muted mb-2"><?= htmlspecialchars((string) $server['domain'], ENT_QUOTES, 'UTF-8'); ?></small>
                    <span class="badge bg-light text-dark border"><?= htmlspecialchars((string) $server['disk_free'], ENT_QUOTES, 'UTF-8'); ?> Free</span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card h-100 border-0 shadow-sm p-3">
                <div class="card-body text-center">
                    <i class="bi bi-database-check h1 text-primary d-block mb-3"></i>
                    <h6 class="fw-bold">MySQL</h6>
                    <small class="d-block text-muted mb-2">v<?= htmlspecialchars((string) explode('-', (string) $mysql['version'])[0], ENT_QUOTES, 'UTF-8'); ?></small>
                    <span class="badge bg-success">Connected</span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card h-100 border-0 shadow-sm p-3">
                <div class="card-body text-center">
                    <i class="bi bi-file-earmark-medical h1 text-primary d-block mb-3"></i>
                    <h6 class="fw-bold">Logs</h6>
                    <small class="d-block text-muted mb-2">APPROOT/logs</small>
                    <?php if ($logs['exists'] && $logs['writable']): ?>
                        <span class="badge bg-success">Writable</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Check Permissions</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <div class="row mt-5 justify-content-center">
        <div class="col-lg-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Environment Paths</h6>
                    <table class="table table-sm mb-0 small">
                        <!-- CMSEC-2026-4830-H — Contextual health output escaping -->
                        <tr><td class="text-muted border-0"></td><td class="fw-mono border-0"><?= htmlspecialchars((string) $server['root'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                        <tr><td class="text-muted">Log Directory</td><td class="fw-mono"><?= htmlspecialchars((string) $logs['path'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                        <tr><td class="text-muted">Database Type</td><td class="fw-mono"><?= htmlspecialchars((string) $mysql['type'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                        <tr><td class="text-muted">Full SQL Version</td><td class="fw-mono"><?= htmlspecialchars((string) $mysql['version'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require APPROOT . '/views/inc/foot.php'; ?>
