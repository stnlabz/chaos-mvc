<?php require APPROOT . '/views/inc/head.php'; ?>

<div class="container mt-4">
    <h1><?= htmlspecialchars($data['title']) ?></h1>

    <p class="text-muted">
        Real-world sites built and running on Chaos MVC.
    </p>

    <hr>

    <?php if (!empty($data['sites'])): ?>
        <?php foreach ($data['sites'] as $site): ?>
            
            <div class="mb-4">
                <h4>
                    <a href="<?= htmlspecialchars($site['url']) ?>" target="_blank">
                        <?= htmlspecialchars($site['name']) ?>
                    </a>
                </h4>

                <p class="text-muted">
                    <?= htmlspecialchars($site['description']) ?>
                </p>

                <?php if (!empty($site['highlights'])): ?>
                    <ul>
                        <?php foreach ($site['highlights'] as $item): ?>
                            <li><?= htmlspecialchars($item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

            </div>

        <?php endforeach; ?>
    <?php else: ?>
        <p>No usage sites available.</p>
    <?php endif; ?>
</div>

<?php require APPROOT . '/views/inc/foot.php'; ?>
