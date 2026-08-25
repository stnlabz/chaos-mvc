<?php require APPROOT . '/views/inc/head.php'; ?>
<p><small><A href="/admin">Admin</a> >> <strong>Posts</strong></small></p>
<div class="cl-container">
    <div class="cl-header">
        <h2>Posts Management</h2>
        <?php if($data['edit_item']): ?> <a href="/admin/posts" class="btn">New Post</a> <?php endif; ?>
    </div>

    <div class="cl-card">
        <form action="/admin/posts" method="POST">
            <input type="hidden" name="id" value="<?= $data['edit_item']['id'] ?? '' ?>">
            <div class="form-group">
                <label>Post Title</label>
                <input type="text" name="title" value="<?= $data['edit_item']['title'] ?? '' ?>" required>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label>Featured Image</label>
                    <select name="featured_image_id">
                        <option value="">No Image</option>
                        <?php foreach($data['media_items'] as $media): ?>
                            <option value="<?= $media['id'] ?>" <?= (isset($data['edit_item']) && $data['edit_item']['featured_image_id'] == $media['id']) ? 'selected' : '' ?>>
                                <?= $media['filename'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="padding-top: 25px;">
                    <label><input type="checkbox" name="published" <?= (isset($data['edit_item']) && $data['edit_item']['published']) ? 'checked' : '' ?>> Live on Site</label>
                </div>
            </div>

            <div class="form-group">
                <label>Content</label>
                <textarea name="body" rows="10" required><?= $data['edit_item']['body'] ?? '' ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Save Post</button>
        </form>
    </div>

    <div class="cl-card">
        <table>
            <thead>
                <tr><th>Status</th><th>Title</th><th>Date</th><th style="text-align:right">Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach($data['items'] as $post): ?>
                <tr>
                    <td><?= $post['published'] ? '🟢' : '⚪' ?></td>
                    <td><strong><?= $post['title'] ?></strong></td>
                    <td><?= date('M j, Y', strtotime($post['created_at'])) ?></td>
                    <td style="text-align:right">
                        <a href="/admin/posts/edit/<?= $post['id'] ?>" class="btn-edit">Edit</a>
                        <a href="/admin/posts/delete/<?= $post['id'] ?>" class="btn-danger" onclick="return confirm('Delete post?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require APPROOT . '/views/inc/foot.php'; ?>
