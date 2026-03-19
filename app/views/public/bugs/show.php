<?php require APPROOT . '/views/inc/head.php'; ?>
<?php
// path: /app/views/public/bugs/show.php
$role = $_SESSION['role'] ?? 'user';
?>
<div class="container">
  <div class="row">
<section class="bug-detail" style="max-width: 900px; margin: 0 auto; padding: 20px;">
    <header style="border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <span style="font-family: monospace; color: #666;">#<?= substr($bug['bug_hash'] ?? '', 0, 6); ?></span>
                <h1 style="margin: 10px 0;"><?= htmlspecialchars($bug['title'] ?? 'untitled'); ?></h1>
                <span style="display: inline-block; padding: 2px 10px; border: 1px solid #333; font-size: 0.8em; font-weight: bold; text-transform: uppercase; background: <?= $bug['status'] === 'open' ? '#fff' : '#eee'; ?>;">
                    <?= htmlspecialchars($bug['status'] ?? 'open'); ?>
                </span>
            </div>

            <?php if (in_array($role, ['admin', 'staff'])): ?>
                <div style="background: #eee; padding: 15px; border: 1px solid #ccc; text-align: right;">
                    <label style="display: block; font-size: 0.7em; font-weight: bold; margin-bottom: 5px;">
                        <?= $role === 'admin' ? 'TIER 3/4 COMMAND' : 'TIER 1/2 TRIAGE'; ?>
                    </label>
                    <form action="/bugs/status/<?= $bug['id']; ?>" method="post">
                        <select name="status" onchange="this.form.submit()" style="padding: 5px; background: #fff; border: 1px solid #333; cursor: pointer;">
                            <?php if ($role === 'admin'): ?>
                                <option value="open" <?= $bug['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                                <option value="pending" <?= $bug['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="closed" <?= $bug['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                            <?php else: // staff tier ?>
                                <option value="open" <?= $bug['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                                <option value="pending" <?= $bug['status'] === 'pending' ? 'selected' : ''; ?>>Set Pending</option>
                            <?php endif; ?>
                        </select>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="bug-body" style="line-height: 1.7; margin-bottom: 40px;">
        <?= nl2br(htmlspecialchars($bug['description'] ?? '')); ?>
    </div>

    <div class="thread-container" style="border-top: 1px solid #eee; padding-top: 20px;">
        <h3 style="text-transform: lowercase;">logs & updates</h3>
        <?php if (!empty($thread)): ?>
            <?php foreach ($thread as $log): ?>
                <div style="margin-bottom: 20px; padding: 15px; border: 1px solid #eee; background: <?= $log['is_admin_update'] ? '#fff' : '#fafafa'; ?>;">
                    <div style="font-size: 0.8em; border-bottom: 1px solid #eee; margin-bottom: 10px; padding-bottom: 5px; display: flex; justify-content: space-between;">
                        <strong><?= strtoupper($log['role']); ?></strong>
                        <span><?= $log['created_at']; ?></span>
                    </div>
                    <div style="font-size: 0.95em;">
                        <?= nl2br(htmlspecialchars($log['comment_text'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="font-style: italic; color: #888;">no logs found for this entry.</p>
        <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="reply-area" style="margin-top: 40px; border-top: 2px solid #333; padding-top: 20px;">
            <form action="/bugs/comment/<?= $bug['id']; ?>" method="post">
                <textarea name="comment_text" style="width: 100%; height: 120px; padding: 10px; border: 1px solid #333; font-family: inherit;" required placeholder="enter update content..."></textarea>
                <button type="submit" style="margin-top: 10px; padding: 10px 25px; background: #333; color: #fff; border: 0; cursor: pointer; font-weight: bold;">post log</button>
            </form>
        </div>
    <?php endif; ?>
</section>
  </div>
</div>
<?php require APPROOT . '/views/inc/foot.php'; ?>
