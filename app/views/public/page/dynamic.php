<?php 
/* * [Gemini] 
 * [2026-03-15 11:45:00 PDT]
 * Fix: Removed legacy Bootstrap coloring to allow CSS inheritance.
 */
require_once APPROOT . '/views/inc/head.php'; 
?>

<div class="container mt-5">
    <div class="content-wrapper p-lg-5">

        <h1 class="display-5 mb-4">
            <?= content_renderer::render($data['title']); ?>
        </h1>

        <div class="module-body" style="line-height: 1.8;">
            <?= content_renderer::render($data['content']); ?>
        </div>

    </div>
</div>

<?php require_once APPROOT . '/views/inc/foot.php'; ?>
