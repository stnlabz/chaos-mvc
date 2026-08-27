<?php require APPROOT . '/views/inc/head.php'; ?>
<div class="container py-5">
    
    <div class="text-center mb-5">
        <h1 class="fw-bold text-uppercase">Admin</h1>
        <p class="text-muted small">Management Suite</p>
    </div>
    
    <?php if (!empty($_SESSION['admin_status'])): ?>
    <div id="admin-status"
         style="position: relative;
                background-color: #e9f5ec;
                color: #1e4620;
                padding: 15px 40px 15px 15px;
                border: 1px solid #c3e6cb;
                border-radius: 4px;
                margin-bottom: 20px;">

        <strong>System Protocol:</strong>
        <?= htmlspecialchars($_SESSION['admin_status']); ?>

        <!-- Close Button -->
        <button onclick="document.getElementById('admin-status').remove();"
                style="position: absolute;
                       right: 10px;
                       top: 8px;
                       background: none;
                       border: none;
                       font-size: 18px;
                       font-weight: bold;
                       cursor: pointer;
                       color: #1e4620;">
            &times;
        </button>
    </div>

    <?php unset($_SESSION['admin_status']); ?>
<?php endif; ?>

    <div class="row g-4 justify-content-center mb-5">
        <?php
        /*
         * CMSEC-2026-4830-B — Inert administration discovery
         *
         * Disabled view-time controller scanning and require_once execution
         * moved into the protected administration controller.
         */
        foreach (($data['modules'] ?? []) as $name) {
            if (is_string($name)) {
                    ?>
                    <div class="col-md-6 col-lg-3 text-center">
                        <div class="card h-100 border-0 shadow-sm p-3">
                            <div class="card-body">
                                <i class="bi bi-gear h1 d-block mb-3 text-primary"></i>
                                <h6 class="fw-bold text-capitalize"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></h6>
                                <a href="/admin/<?= rawurlencode($name); ?>" class="btn btn-outline-primary btn-sm w-100 mt-2">Manage</a>
                            </div>
                        </div>
                    </div>
                    <?php
            }
        }
        ?>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="p-4 bg-light rounded-3 d-flex justify-content-between align-items-center border">
                <div>
                    <span class="fw-bold d-block text-uppercase small">Core Maintenance</span>
                    <small class="text-muted">Rebuild sitemaps, ROR files, and search indices.</small>
                </div>
                <a href="/admin/refresh_indices" class="btn btn-primary btn-sm px-4">Refresh Indices</a>
            </div>
        </div>
    </div>
</div>
<form action="/logout" method="POST">
    <?= $this->csrf_field(); ?>
    <button type="submit" style="background: none; border: 0; color: inherit; cursor: pointer; font: inherit; padding: 0;"><small>Logout</small></button>
</form>
<?php require APPROOT . '/views/inc/foot.php'; ?>
