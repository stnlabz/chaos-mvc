<?php require APPROOT . '/views/inc/head.php'; ?>
<p><small><a href="/admin">Admin</a> >> <strong>Modules</strong></small></p>
<div class="container py-5">
    <div class="mb-5">
        <h2 class="fw-bold">Module Registry</h2>
        <p class="text-muted small text-uppercase">System Architecture Manager</p>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle border">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Module Slug</th>
                    <th>Classification</th>
                    <th class="text-end pe-4">System Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($data['modules'] as $module): ?>
                <tr>
                    <td class="ps-4">
                        <code class="fw-bold"><?= $module['slug']; ?></code>
                    </td>
                    <td>
                        <?php if($module['is_core']): ?>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">CORE</span>
                        <?php else: ?>
                            <span class="badge bg-light text-dark border">ADDON</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end pe-4">
                        <a href="/admin/<?= $module['slug']; ?>" class="btn btn-sm btn-outline-primary px-3">Manage</a>
                        
                        <?php if(!$module['is_core']): ?>
                            <form action="/admin/uninstall" method="POST" class="d-inline" 
                                  onsubmit="return confirm('EXTREME DANGER: This will permanently delete the DB table, Controller, Model, and all Views for this module.');">
                                <input type="hidden" name="module" value="<?= $module['slug']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger px-3 ms-2">Nuke</button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-sm btn-light ms-2" disabled title="Core modules cannot be removed.">
                                <i class="bi bi-lock-fill"></i>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require APPROOT . '/views/inc/foot.php'; ?>
