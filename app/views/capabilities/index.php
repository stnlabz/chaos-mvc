<?php require APPROOT . '/views/inc/head.php'; ?>

<div class="container mt-4">
    <h1><?= htmlspecialchars($data['title']) ?></h1>

    <p class="text-muted">
        A snapshot of what Chaos MVC can do right now.
    </p>

    <hr>

    <?php if (!empty($data['items'])): ?>
        <?php foreach ($data['items'] as $item): 
            $status = $item['status'];

            if ($status === 'complete') {
                $color = '#198754'; // green
                $label = 'Complete';
            } elseif ($status === 'active') {
                $color = '#0dcaf0'; // blue
                $label = 'Active';
            } else {
                $color = '#dc3545'; // red
                $label = 'Planned';
            }
        ?>

        <div class="mb-4">
            <h4>
                <?= htmlspecialchars($item['name']) ?>
                <span style="color: <?= $color ?>;">[<?= $label ?>]</span>
            </h4>

            <p class="text-muted">
                <?= htmlspecialchars($item['description']) ?>
            </p>
        </div>

        <?php endforeach; ?>
    <?php else: ?>
        <p>No capabilities defined.</p>
    <?php endif; ?>
</div>

<?php require APPROOT . '/views/inc/foot.php'; ?>
