<?php /* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */ ?>
<?php require APPROOT . '/views/inc/head.php'; ?>

<main class="container py-5">
    <h1><?= htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8') ?></h1>
    <ul>
        <?php foreach ($data['items'] as $item): ?>
            <li><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
    </ul>
</main>

<?php require APPROOT . '/views/inc/foot.php'; ?>
<?php /* [End AI:GPT-5.6 Sol] */ ?>
