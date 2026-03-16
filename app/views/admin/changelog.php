<?php require APPROOT . '/views/inc/head.php'; ?>
<p><small><A href="/admin">Admin</a> >> <strong>Announcements</strong></small></p>
<style>
    .cl-container { font-family: sans-serif; max-width: 1000px; margin: 0 auto; color: #333; }
    .cl-card { background: #fff; border: 1px solid #e1e4e8; border-radius: 6px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .cl-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f1f1; padding-bottom: 10px; }
    .btn { padding: 8px 16px; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 14px; border: none; }
    .btn-primary { background: #24292e; color: #fff; }
    .btn-danger { color: #d73a49; background: none; }
    .btn-edit { color: #0366d6; background: none; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th { text-align: left; font-size: 12px; text-transform: uppercase; color: #6a737d; padding: 12px; border-bottom: 2px solid #f1f1f1; }
    td { padding: 12px; border-bottom: 1px solid #f6f8fa; vertical-align: top; }
    .form-group { margin-bottom: 15px; }
    label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 13px; }
    input, textarea { width: 100%; padding: 10px; border: 1px solid #d1d5da; border-radius: 3px; box-sizing: border-box; }
</style>

<div class="cl-container">
    <div class="cl-header">
        <h2>System Changelog</h2>
        <?php if($data['edit_item']): ?>
            <a href="/admin/changelog" class="btn">Cancel Edit</a>
        <?php endif; ?>
    </div>

    <div class="cl-card">
        <h3><?= $data['edit_item'] ? 'Edit Update' : 'Log New Improvement' ?></h3>
        <form action="/admin/changelog" method="POST">
            <input type="hidden" name="id" value="<?= $data['edit_item']['id'] ?? '' ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label>Version Number</label>
                    <input type="text" name="version" placeholder="v1.0.0" value="<?= $data['edit_item']['version'] ?? '' ?>" required>
                </div>
                <div class="form-group">
                    <label>Release Date</label>
                    <input type="date" name="date_released" value="<?= $data['edit_item']['date_released'] ?? date('Y-m-d') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Change Description</label>
                <textarea name="description" rows="4" placeholder="Mention bug fixes, new features, or optimizations..." required><?= $data['edit_item']['description'] ?? '' ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><?= $data['edit_item'] ? 'Save Changes' : 'Publish to Log' ?></button>
        </form>
    </div>

    <div class="cl-card">
        <table>
            <thead>
                <tr>
                    <th>Version</th>
                    <th>Date</th>
                    <th>Summary</th>
                    <th style="text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['items'] as $item): ?>
                <tr>
                    <td><strong><?= $item['version'] ?></strong></td>
                    <td style="white-space:nowrap; font-size: 13px; color: #586069;"><?= date('M j, Y', strtotime($item['date_released'])) ?></td>
                    <td style="font-size: 14px; line-height: 1.5;"><?= nl2br($item['description']) ?></td>
                    <td style="text-align:right; white-space:nowrap;">
                        <a href="/admin/changelog/edit/<?= $item['id'] ?>" class="btn btn-edit">Edit</a>
                        <a href="/admin/changelog/delete/<?= $item['id'] ?>" class="btn btn-danger" onclick="return confirm('Archive this log?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require APPROOT . '/views/inc/foot.php'; ?>
