<?php require APPROOT . '/views/inc/head.php'; ?>
<p><small><A href="/admin">Admin</a> >> <strong>Announcements</strong></small></p>
<div class="container my-3 text-light"> <h2 class="h5 mb-3">Announcements — Management</h2>

    <form method="post" class="card bg-dark text-white border-secondary card-body mb-4">
        <div class="mb-2">
            <label class="form-label">Title</label>
            <input name="title" class="form-control form-control-sm bg-dark text-white border-secondary" required>
        </div>
        <div class="mb-2">
            <label class="form-label">Body</label>
            <textarea name="body" class="form-control form-control-sm bg-dark text-white border-secondary" rows="3" required></textarea>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input bg-dark border-secondary" type="checkbox" name="published" id="pub" checked>
            <label class="form-check-label" for="pub">Published</label>
        </div>
        <button class="btn btn-sm btn-outline-primary">Add Announcement</button>
    </form>

    <table class="table table-sm table-dark table-hover align-middle border-secondary">
        <thead>
            <tr class="text-secondary">
                <th>Date</th>
                <th>Title</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($data['items'])): ?>
            <?php foreach ($data['items'] as $it): ?>
                <tr>
                    <td class="text-secondary small"><?= $it['created_at'] ?></td>
                    <td><?= htmlspecialchars($it['title']) ?></td>
                    <td>
                        <span class="badge <?= $it['published'] ? 'text-bg-success' : 'text-bg-dark border border-secondary' ?>">
                            <?= $it['published'] ? 'Published' : 'Draft' ?>
                        </span>
                    </td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="delete_id" value="<?= $it['id'] ?>">
                            <button class="btn btn-sm btn-link text-danger p-0" onclick="return confirm('Delete?')">×</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="4" class="text-center text-secondary py-4">No announcements found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require APPROOT . '/views/inc/foot.php'; ?>
