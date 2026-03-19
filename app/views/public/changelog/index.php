<?php require APPROOT . '/views/inc/head.php';?>

<div class="container">
    <h1>MVC Project Changelog</h1>
    <hr>
    <?php if (!empty($data['updates'])): ?>
        <?php foreach ($data['updates'] as $update): ?>
            <div class="update-entry" style="margin-bottom: 30px;">
                <h3>
                    <?= htmlspecialchars($update['version']) ?> 
                    <small style="color: #666;">(<?= $update['category'] ?>)</small>
                </h3>
                <p><em>Released on <?= $update['date_released'] ?></em></p>
                
                <div class="update-description">
                    <?= $render_md->markdown($update['description']) ?>
                </div>
            </div>
            <hr>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No updates recorded yet.</p>
    <?php endif; ?>
</div>

<?php require APPROOT . '/views/inc/foot.php'; ?>
