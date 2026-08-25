<?php /* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */ ?>
<?php require APPROOT . '/views/inc/head.php'; ?>

<main class="container py-5">
    <h1><?= htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8') ?></h1>
    <p><?= htmlspecialchars($data['description'], ENT_QUOTES, 'UTF-8') ?></p>

    <nav aria-label="Developer topics">
        <a href="/developer">Overview</a> ·
        <a href="/developer/flow">Flow</a> ·
        <a href="/developer/example">Example</a> ·
        <a href="/developer/database">Database</a> ·
        <a href="/developer/markdown">Markdown</a> ·
        <a href="/developer/theme">Views</a> ·
        <a href="/developer/rules">Rules</a>
    </nav>
</main>

<?php require APPROOT . '/views/inc/foot.php'; ?>
<?php /* [End AI:GPT-5.6 Sol] */ ?>
