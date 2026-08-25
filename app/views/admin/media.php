<?php require APPROOT . '/views/inc/head.php'; ?>
<p><small><A href="/admin">Admin</a> >> <strong>Media</strong></small></p>
<div class="cl-container">
    <div class="cl-header">
        <h2>Media Library</h2>
    </div>

    <div class="cl-card" style="border: 2px dashed #d1d5da; text-align: center; padding: 40px;">
        <form action="/admin/media" method="POST" enctype="multipart/form-data">
            <input type="file" name="file" required>
            <button type="submit" class="btn btn-primary">Upload Asset</button>
        </form>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px;">
        <?php foreach ($data['items'] as $item): ?>
            <div class="cl-card" style="padding: 10px; text-align: center;">
                <div style="height: 120px; display: flex; align-items: center; justify-content: center; background: #f6f8fa; margin-bottom: 10px;">
                    <img src="<?= $item['file_path'] ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                </div>
                <small style="display: block; margin-bottom: 8px; color: #6a737d;"><?= $item['filename'] ?></small>
                <div style="display: flex; justify-content: space-between;">
                    <button class="btn-edit" onclick="navigator.clipboard.writeText('<?= $item['file_path'] ?>'); alert('Copied!');">URL</button>
                    <a href="/admin/media/delete/<?= $item['id'] ?>" class="btn-danger" onclick="return confirm('Delete permanently?')">Delete</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php require APPROOT . '/views/inc/foot.php'; ?>
