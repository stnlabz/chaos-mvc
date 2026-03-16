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
                    <small class="d-block text-muted mb-2"><?= $server['software']; ?></small>
                    <span class="badge bg-primary">PHP <?= $server['php_version']; ?></span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card h-100 border-0 shadow-sm p-3">
                <div class="card-body text-center">
                    <i class="bi bi-hdd-network h1 text-primary d-block mb-3"></i>
                    <h6 class="fw-bold">Storage</h6>
                    <small class="d-block text-muted mb-2"><?= $server['domain']; ?></small>
                    <span class="badge bg-light text-dark border"><?= $server['disk_free']; ?> Free</span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card h-100 border-0 shadow-sm p-3">
                <div class="card-body text-center">
                    <i class="bi bi-database-check h1 text-primary d-block mb-3"></i>
                    <h6 class="fw-bold">MySQL</h6>
                    <small class="d-block text-muted mb-2">v<?= explode('-', $mysql['version'])[0]; ?></small>
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
                        <tr><td class="text-muted border-0"></td><td class="fw-mono border-0"><?= $server['root']; ?></td></tr>
                        <tr><td class="text-muted">Log Directory</td><td class="fw-mono"><?= $logs['path']; ?></td></tr>
                        <tr><td class="text-muted">Database Type</td><td class="fw-mono"><?= $mysql['type']; ?></td></tr>
                        <tr><td class="text-muted">Full SQL Version</td><td class="fw-mono"><?= $mysql['version']; ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require APPROOT . '/views/inc/foot.php'; ?>
