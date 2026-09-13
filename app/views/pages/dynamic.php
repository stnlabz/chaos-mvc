<?php
// path: /app/views/pages/dynamic.php

if (!theme::render('head', get_defined_vars())) {
    require APPROOT . '/views/inc/head.php';
}

$title = (string) ($data['title'] ?? '');
$contentFile = (string) ($data['content_file'] ?? '');
$contentPath = (string) ($data['content_path'] ?? '');
$content = (string) ($data['content'] ?? '');
?>

<main class="page">
    <div class="content-wrap">
        <article class="page-content">
            <h1>
                <?= htmlspecialchars(
                    $title,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            </h1>

            <div class="page-body">
                <?php if ($contentFile === 'main.md'): ?>
                    <?php
                    if (
                        $contentPath !== ''
                        && is_file($contentPath)
                    ) {
                        $render_md->markdown_file($contentPath);
                    }
                    ?>
                <?php elseif ($contentFile === 'main.html'): ?>
                    <?= $content; ?>
                <?php elseif ($contentFile === 'main.json'): ?>
                    <?php
                    /* [AI:GPT-5.6 Sol | 2026-09-13 19:30:45 UTC] */
                    $decodedJson = json_decode($content, true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        $displayJson = json_encode(
                            $decodedJson,
                            JSON_PRETTY_PRINT
                            | JSON_UNESCAPED_SLASHES
                            | JSON_UNESCAPED_UNICODE
                        );

                        if (!is_string($displayJson)) {
                            $displayJson = $content;
                        }
                    } else {
                        $displayJson = $content;
                    }
                    /* [End AI:GPT-5.6 Sol] */
                    ?>
                    <pre><code class="language-json"><?= htmlspecialchars(
                        $displayJson,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?></code></pre>
                <?php else: ?>
                    <pre><?= htmlspecialchars(
                        $content,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?></pre>
                <?php endif; ?>
            </div>
        </article>
    </div>
</main>

<?php
if (!theme::render('foot', get_defined_vars())) {
    require APPROOT . '/views/inc/foot.php';
}
